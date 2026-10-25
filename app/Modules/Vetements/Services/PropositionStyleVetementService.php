<?php

namespace App\Modules\Vetements\Services;

use App\Modules\Vetements\Exceptions\StyleVetementException;
use App\Modules\Vetements\Models\Vetement;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PropositionStyleVetementService
{
    private const MODELE_TEXTE = 'gemini-3.5-flash-lite';

    private const MODELE_TEXTE_SECOURS = 'gemini-flash-lite-latest';

    /** @var list<string> */
    private const MODELES_IMAGE = [
        'gemini-3.1-flash-lite-image',
        'gemini-2.5-flash-image',
    ];

    public function estConfigure(): bool
    {
        return trim((string) config('services.vision.gemini_key')) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function proposer(UploadedFile $photo): array
    {
        if (! $this->estConfigure()) {
            throw new StyleVetementException('Le nouveau style n\'est pas activé. Ajoutez GEMINI_API_KEY.');
        }

        $binaire = file_get_contents($photo->getRealPath() ?: '');
        if ($binaire === false || $binaire === '') {
            throw new StyleVetementException('La photo est vide ou illisible.');
        }

        $image = [
            'mime' => ($photo->getMimeType() ?: 'image/jpeg') === 'image/jpg' ? 'image/jpeg' : ($photo->getMimeType() ?: 'image/jpeg'),
            'data' => base64_encode($binaire),
        ];
        $cle = 'vetement-style:'.hash('sha256', $binaire);
        $cache = Cache::get($cle);

        if (is_array($cache) && ! empty($cache['vue'])) {
            return $cache;
        }

        $style = is_array($cache['style'] ?? null) ? $cache['style'] : $this->lireStyle($image);
        if (empty($style['est_vetement'])) {
            return [
                'est_vetement' => false,
                'message' => 'La photo ne montre pas un vêtement à restyler. Photographiez la pièce entière, à plat.',
            ];
        }

        $vue = $this->genererVue($image, $style);
        $resultat = $this->assembler($style, $vue);

        Cache::put($cle, $vue !== null ? $resultat : ['style' => $style], now()->addMinutes($vue !== null ? 20 : 10));

        return $resultat;
    }

    /**
     * @param  array{mime: string, data: string}  $image
     * @return array<string, mixed>
     */
    private function lireStyle(array $image): array
    {
        $types = implode(' | ', Vetement::TYPES);
        $consigne = <<<TXT
Tu es styliste de réparation textile. Observe la photo d'une pièce à réparer.
Réponds uniquement avec un objet JSON :
{
  "est_vetement": boolean,
  "type": "{$types}",
  "titre": string,
  "style": string,
  "description": string,
  "couleurs": ["#RRGGBB", "#RRGGBB", "#RRGGBB"],
  "gestes": [string, string, string]
}
Règles :
- est_vetement est false si la photo n'est pas un vêtement.
- Le nouveau style naît de la réparation : reprise visible, surpiqûre contrastée, pièce rapportée, teinture ou découpe.
- Garde le même type de vêtement. Ne décris pas une personne.
- couleurs : tissu, couture contrastée, pièce rapportée. Trois codes hexadécimaux.
- description : 2 phrases en français.
- gestes : 3 actions d'atelier courtes.
TXT;

        $texte = $this->interrogerTexte($consigne, $image);

        return $this->normaliserStyle($this->extraireJson($texte));
    }

    /**
     * @param  array{mime: string, data: string}  $image
     * @param  array<string, mixed>  $style
     */
    private function genererVue(array $image, array $style): ?string
    {
        $consigne = sprintf(
            'Dessine uniquement ce vêtement après réparation, sans personne ni visage, fond clair uni, vue de face. Type : %s. Style : %s. %s',
            $style['type'],
            $style['style'],
            $style['description']
        );

        foreach (self::MODELES_IMAGE as $modele) {
            try {
                $reponse = Http::timeout(40)
                    ->acceptJson()
                    ->withHeaders(['x-goog-api-key' => $this->cle()])
                    ->post($this->url($modele), [
                        'contents' => [[
                            'parts' => [
                                ['text' => $consigne],
                                ['inlineData' => [
                                    'mimeType' => $image['mime'],
                                    'data' => $image['data'],
                                ]],
                            ],
                        ]],
                        'generationConfig' => [
                            'responseModalities' => ['TEXT', 'IMAGE'],
                        ],
                    ]);
            } catch (ConnectionException $exception) {
                Log::warning('Rendu de style injoignable', ['modele' => $modele]);

                continue;
            }

            if ($reponse->successful()) {
                $vue = $this->imageDataUrl($reponse);
                if ($vue !== null) {
                    return $vue;
                }
            }

            Log::warning('Rendu de style indisponible', [
                'modele' => $modele,
                'statut' => $reponse->status(),
            ]);
        }

        return null;
    }

    /**
     * @param  array{mime: string, data: string}  $image
     */
    private function interrogerTexte(string $consigne, array $image): string
    {
        $modeles = array_values(array_unique(array_filter([
            (string) config('services.vision.gemini_model', self::MODELE_TEXTE),
            self::MODELE_TEXTE,
            self::MODELE_TEXTE_SECOURS,
        ])));
        $dernier = null;

        foreach ($modeles as $indice => $modele) {
            try {
                $reponse = Http::timeout(20)
                    ->acceptJson()
                    ->withHeaders(['x-goog-api-key' => $this->cle()])
                    ->post($this->url($modele), [
                        'contents' => [[
                            'parts' => [
                                ['text' => $consigne],
                                ['inlineData' => [
                                    'mimeType' => $image['mime'],
                                    'data' => $image['data'],
                                ]],
                            ],
                        ]],
                        'generationConfig' => [
                            'temperature' => 0.4,
                            'responseMimeType' => 'application/json',
                        ],
                    ]);
            } catch (ConnectionException $exception) {
                Log::warning('Style textile injoignable', ['modele' => $modele]);
                if ($indice === count($modeles) - 1) {
                    throw new StyleVetementException('Le styliste ne répond pas. Réessayez dans un instant.');
                }

                continue;
            }

            if ($reponse->successful()) {
                $texte = $this->texte($reponse);
                if ($texte !== '') {
                    return $texte;
                }
            }

            $dernier = $reponse;
            if (! in_array($reponse->status(), [404, 429, 503], true) || $indice === count($modeles) - 1) {
                break;
            }
        }

        if ($dernier instanceof Response && $dernier->status() === 429) {
            throw new StyleVetementException('Le styliste est saturé. Réessayez dans un instant.');
        }

        throw new StyleVetementException('Le nouveau style n\'a pas pu être proposé. Essayez une autre photo.');
    }

    /**
     * @param  array<string, mixed>  $style
     * @return array<string, mixed>
     */
    private function assembler(array $style, ?string $vue): array
    {
        return [
            'est_vetement' => true,
            'type' => $style['type'],
            'titre' => $style['titre'],
            'style' => $style['style'],
            'description' => $style['description'],
            'couleurs' => $style['couleurs'],
            'gestes' => $style['gestes'],
            'vue' => $vue,
        ];
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function normaliserStyle(array $donnees): array
    {
        $estVetement = ($donnees['est_vetement'] ?? false) === true;
        $type = in_array($donnees['type'] ?? '', Vetement::TYPES, true) ? $donnees['type'] : 'Veste';
        $couleursBrutes = is_array($donnees['couleurs'] ?? null) ? $donnees['couleurs'] : [];
        $defauts = ['#243E73', '#E8B931', '#F3EDE2'];
        $couleurs = [];
        foreach ([0, 1, 2] as $index) {
            $couleurs[] = $this->couleur((string) ($couleursBrutes[$index] ?? ''), $defauts[$index]);
        }

        $gestes = [];
        foreach (is_array($donnees['gestes'] ?? null) ? $donnees['gestes'] : [] as $geste) {
            $geste = trim((string) $geste);
            if ($geste !== '') {
                $gestes[] = mb_substr($geste, 0, 140);
            }
            if (count($gestes) === 3) {
                break;
            }
        }
        if ($gestes === []) {
            $gestes = ['Reprendre les coutures en fil contrasté', 'Poser une pièce rapportée', 'Teindre le tissu pour unifier la réparation'];
        }

        return [
            'est_vetement' => $estVetement,
            'type' => $type,
            'titre' => mb_substr(trim((string) ($donnees['titre'] ?? 'Nouveau style')), 0, 80) ?: 'Nouveau style',
            'style' => mb_substr(trim((string) ($donnees['style'] ?? 'Reprise visible')), 0, 80) ?: 'Reprise visible',
            'description' => mb_substr(trim((string) ($donnees['description'] ?? '')), 0, 500),
            'couleurs' => $couleurs,
            'gestes' => $gestes,
        ];
    }

    private function couleur(string $valeur, string $defaut): string
    {
        $valeur = trim($valeur);
        if (preg_match('/^#?[0-9a-fA-F]{6}$/', $valeur)) {
            return str_starts_with($valeur, '#') ? strtoupper($valeur) : '#'.strtoupper($valeur);
        }

        $normalise = mb_strtolower($valeur);
        $noms = [
            'noir' => '#1C1C1C', 'blanc' => '#F4F1EA', 'bleu' => '#1D4E89', 'marine' => '#1B3A4B',
            'rouge' => '#9B2335', 'vert' => '#2E7D32', 'jaune' => '#E8B931', 'orange' => '#E88932',
            'rose' => '#C48B9F', 'beige' => '#E6D3B3', 'gris' => '#8D8F8E', 'marron' => '#6B4423',
            'camel' => '#C19A6B', 'indigo' => '#243E73', 'ecru' => '#F3EDE2',
        ];
        foreach ($noms as $nom => $hex) {
            if (str_contains($normalise, $nom)) {
                return $hex;
            }
        }

        return $defaut;
    }

    private function url(string $modele): string
    {
        return sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            rawurlencode($modele)
        );
    }

    private function texte(Response $reponse): string
    {
        $texte = '';
        foreach ($reponse->json('candidates.0.content.parts') ?? [] as $part) {
            if (is_array($part) && isset($part['text']) && empty($part['thought'])) {
                $texte .= $part['text'];
            }
        }

        return trim($texte);
    }

    private function imageDataUrl(Response $reponse): ?string
    {
        foreach ($reponse->json('candidates.0.content.parts') ?? [] as $part) {
            if (! is_array($part)) {
                continue;
            }
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (! is_array($inline) || empty($inline['data'])) {
                continue;
            }
            $mime = (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png');
            if (! str_starts_with($mime, 'image/')) {
                continue;
            }

            return 'data:'.$mime.';base64,'.$inline['data'];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function extraireJson(string $texte): array
    {
        $texte = trim(preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($texte)) ?? $texte);
        $debut = strpos($texte, '{');
        $fin = strrpos($texte, '}');
        if ($debut === false || $fin === false || $fin <= $debut) {
            return [];
        }
        $donnees = json_decode(substr($texte, $debut, $fin - $debut + 1), true);

        return is_array($donnees) ? $donnees : [];
    }

    private function cle(): string
    {
        return trim((string) config('services.vision.gemini_key'));
    }
}
