<?php

namespace App\Modules\Vetements\Services;

use App\Modules\Vetements\Exceptions\AnalyseImageException;
use App\Modules\Vetements\Models\Vetement;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnalyseImageVetementService
{
    private const MODELE_GEMINI_DEFAUT = 'gemini-3.5-flash-lite';

    private const MODELE_GEMINI_SECOURS = 'gemini-flash-lite-latest';

    private const DELAI_GEMINI = 20;

    private ?string $modeleUtilise = null;

    public function estConfigure(): bool
    {
        return $this->cle($this->provider()) !== '';
    }

    public function provider(): string
    {
        $demande = (string) config('services.vision.provider', 'gemini');
        $gemini = trim((string) config('services.vision.gemini_key'));
        $openai = trim((string) config('services.vision.openai_key'));

        if ($demande === 'openai' && $openai !== '') {
            return 'openai';
        }

        if ($gemini !== '') {
            return 'gemini';
        }

        if ($openai !== '') {
            return 'openai';
        }

        return $demande === 'openai' ? 'openai' : 'gemini';
    }

    public function modele(): string
    {
        if ($this->provider() === 'openai') {
            return (string) config('services.vision.openai_model', 'gpt-4o-mini');
        }

        return (string) config('services.vision.gemini_model', self::MODELE_GEMINI_DEFAUT);
    }

    public function analyser(UploadedFile $photo): ResultatAnalyseVetement
    {
        if (! $this->estConfigure()) {
            throw new AnalyseImageException('L\'analyse photo n\'est pas activée. Ajoutez GEMINI_API_KEY ou OPENAI_API_KEY.');
        }

        $empreinte = hash_file('sha256', $photo->getRealPath() ?: '');
        $cleCache = 'vetement-analyse:'.$this->provider().':'.$this->modele().':'.$empreinte;
        $enCache = Cache::get($cleCache);

        if (is_array($enCache)) {
            return ResultatAnalyseVetement::depuisTableau($enCache);
        }

        $image = $this->preparerImage($photo);

        try {
            $texte = $this->provider() === 'openai'
                ? $this->interrogerOpenAi($image)
                : $this->interrogerGemini($image);
        } catch (ConnectionException $exception) {
            Log::warning('Analyse image vêtement injoignable', [
                'fournisseur' => $this->provider(),
            ]);

            throw new AnalyseImageException('Le service d\'analyse d\'image ne répond pas. Réessayez dans un instant.');
        }

        $resultat = ResultatAnalyseVetement::depuisModele(
            $this->extraireJson($texte),
            $this->modeleUtilise ?? $this->modele()
        );
        Cache::put($cleCache, $resultat->toArray(), now()->addMinutes(30));

        return $resultat;
    }

    /**
     * @param  array{mime: string, data: string}  $image
     */
    private function interrogerGemini(array $image): string
    {
        $modeles = array_values(array_unique(array_filter([
            $this->modele(),
            self::MODELE_GEMINI_DEFAUT,
            self::MODELE_GEMINI_SECOURS,
        ])));
        $dernierEchec = null;

        foreach ($modeles as $indice => $modele) {
            try {
                $reponse = Http::timeout(self::DELAI_GEMINI)
                    ->acceptJson()
                    ->withHeaders(['x-goog-api-key' => $this->cle('gemini')])
                    ->post($this->urlGemini($modele), [
                        'contents' => [[
                            'parts' => [
                                ['text' => $this->consigne()],
                                ['inlineData' => [
                                    'mimeType' => $image['mime'],
                                    'data' => $image['data'],
                                ]],
                            ],
                        ]],
                        'generationConfig' => [
                            'temperature' => 0.2,
                            'responseMimeType' => 'application/json',
                        ],
                    ]);
            } catch (ConnectionException $exception) {
                Log::warning('Analyse image vêtement injoignable', [
                    'fournisseur' => 'gemini',
                    'modele' => $modele,
                ]);

                if ($indice === count($modeles) - 1) {
                    throw new AnalyseImageException('Le service d\'analyse d\'image ne répond pas. Réessayez dans un instant.');
                }

                continue;
            }

            if ($reponse->successful()) {
                $this->modeleUtilise = $modele;

                return $this->texteGemini($reponse);
            }

            $reessayer = in_array($reponse->status(), [404, 429, 503], true)
                && $indice < count($modeles) - 1;

            if ($reessayer) {
                Log::warning('Modèle Gemini indisponible, nouvel essai', [
                    'modele' => $modele,
                    'statut' => $reponse->status(),
                ]);
                $dernierEchec = $reponse;

                continue;
            }

            $this->verifierReponse($reponse);
        }

        if ($dernierEchec instanceof Response) {
            $this->verifierReponse($dernierEchec);
        }

        throw new AnalyseImageException('La photo n\'a pas pu être analysée. Essayez une autre image.');
    }

    private function urlGemini(string $modele): string
    {
        return sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            rawurlencode($modele)
        );
    }

    private function texteGemini(Response $reponse): string
    {
        $texte = '';
        foreach ($reponse->json('candidates.0.content.parts') ?? [] as $part) {
            if (is_array($part) && isset($part['text']) && empty($part['thought'])) {
                $texte .= $part['text'];
            }
        }

        if (trim($texte) === '') {
            throw new AnalyseImageException('La photo n\'a pas pu être analysée. Essayez une autre image.');
        }

        return $texte;
    }

    /**
     * @param  array{mime: string, data: string}  $image
     */
    private function interrogerOpenAi(array $image): string
    {
        $reponse = Http::withToken($this->cle('openai'))
            ->timeout(30)
            ->acceptJson()
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->modele(),
                'temperature' => 0.2,
                'response_format' => ['type' => 'json_object'],
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $this->consigne()],
                        ['type' => 'image_url', 'image_url' => [
                            'url' => 'data:'.$image['mime'].';base64,'.$image['data'],
                        ]],
                    ],
                ]],
            ]);

        $this->verifierReponse($reponse);

        $contenu = $reponse->json('choices.0.message.content');
        if (is_string($contenu) && trim($contenu) !== '') {
            return $contenu;
        }

        if (is_array($contenu)) {
            $texte = '';
            foreach ($contenu as $part) {
                if (is_array($part) && isset($part['text'])) {
                    $texte .= $part['text'];
                }
            }
            if (trim($texte) !== '') {
                return $texte;
            }
        }

        throw new AnalyseImageException('La photo n\'a pas pu être analysée. Essayez une autre image.');
    }

    private function verifierReponse(Response $reponse): void
    {
        if ($reponse->successful()) {
            return;
        }

        Log::warning('Analyse image vêtement échouée', [
            'statut' => $reponse->status(),
            'fournisseur' => $this->provider(),
        ]);

        $message = match ($reponse->status()) {
            401, 403 => 'La clé d\'analyse d\'image a été refusée. Vérifiez GEMINI_API_KEY ou OPENAI_API_KEY.',
            404 => 'Le modèle d\'analyse configuré n\'est plus disponible. Réglez GEMINI_MODEL=gemini-3.5-flash-lite.',
            429, 503 => 'Le service d\'analyse est saturé. Réessayez dans un instant.',
            default => 'Le service d\'analyse d\'image est momentanément indisponible.',
        };

        throw new AnalyseImageException($message);
    }

    /**
     * @return array<string, mixed>
     */
    private function extraireJson(string $texte): array
    {
        $texte = trim($texte);
        $texte = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $texte) ?? $texte;
        $debut = strpos($texte, '{');
        $fin = strrpos($texte, '}');

        if ($debut === false || $fin === false || $fin <= $debut) {
            throw new AnalyseImageException('Le modèle n\'a pas renvoyé une analyse exploitable.');
        }

        $donnees = json_decode(substr($texte, $debut, $fin - $debut + 1), true);
        if (! is_array($donnees)) {
            throw new AnalyseImageException('Le modèle n\'a pas renvoyé une analyse exploitable.');
        }

        return $donnees;
    }

    /**
     * @return array{mime: string, data: string}
     */
    private function preparerImage(UploadedFile $photo): array
    {
        $binaire = file_get_contents($photo->getRealPath() ?: '');
        $mime = $photo->getMimeType() ?: 'image/jpeg';

        if ($binaire === false || $binaire === '') {
            throw new AnalyseImageException('La photo est vide ou illisible.');
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return [
                'mime' => $mime === 'image/jpg' ? 'image/jpeg' : $mime,
                'data' => base64_encode($binaire),
            ];
        }

        $image = @imagecreatefromstring($binaire);
        if ($image === false) {
            return [
                'mime' => $mime === 'image/jpg' ? 'image/jpeg' : $mime,
                'data' => base64_encode($binaire),
            ];
        }

        $largeur = imagesx($image);
        $hauteur = imagesy($image);
        $max = 1280;
        $ratio = min(1, $max / max($largeur, 1), $max / max($hauteur, 1));
        $nouvelleLargeur = max(1, (int) round($largeur * $ratio));
        $nouvelleHauteur = max(1, (int) round($hauteur * $ratio));

        $cadre = imagecreatetruecolor($nouvelleLargeur, $nouvelleHauteur);
        $blanc = imagecolorallocate($cadre, 255, 255, 255);
        imagefill($cadre, 0, 0, $blanc);
        imagecopyresampled($cadre, $image, 0, 0, 0, 0, $nouvelleLargeur, $nouvelleHauteur, $largeur, $hauteur);
        imagedestroy($image);

        ob_start();
        imagejpeg($cadre, null, 82);
        $jpeg = ob_get_clean();
        imagedestroy($cadre);

        if (! is_string($jpeg) || $jpeg === '') {
            return [
                'mime' => $mime === 'image/jpg' ? 'image/jpeg' : $mime,
                'data' => base64_encode($binaire),
            ];
        }

        return [
            'mime' => 'image/jpeg',
            'data' => base64_encode($jpeg),
        ];
    }

    private function consigne(): string
    {
        $types = implode(' | ', array_merge(Vetement::TYPES, ['Autre']));
        $etats = implode(' | ', Vetement::ETATS);

        return <<<TXT
Tu analyses une photo pour une plateforme de seconde vie textile.
Réponds uniquement avec un objet JSON, sans markdown.
Schéma :
{
  "est_vetement": boolean,
  "type": "{$types}",
  "matiere": string,
  "etat": "{$etats}",
  "defauts": string[],
  "gravite": "aucune" | "legere" | "moyenne" | "severe",
  "reparable": boolean,
  "confiance": integer
}
Règles :
- est_vetement est false si la photo ne montre pas un vêtement porté ou à plat (paysage, visage seul, nourriture, objet).
- etat Excellent ou Bon état : pièce portable sans réparation nécessaire.
- etat À réparer : défaut localisé qu'un atelier peut corriger (bouton, ourlet, petit accroc, couture, tache traitable).
- etat Abîmé : dégâts étendus.
- gravite severe : trou large, tissu déchiré sur une zone structurante, moisissure, brûlure, pièce inutilisable.
- reparable est true seulement si une retouche d'atelier peut rendre la pièce portable.
- defauts : observations courtes en français, tableau vide si aucun défaut visible.
- confiance : entier de 0 à 100.
TXT;
    }

    private function cle(string $provider): string
    {
        $cle = $provider === 'openai'
            ? config('services.vision.openai_key')
            : config('services.vision.gemini_key');

        return trim((string) $cle);
    }
}
