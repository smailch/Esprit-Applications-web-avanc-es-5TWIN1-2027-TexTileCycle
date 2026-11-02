<?php

namespace App\Modules\Ateliers\Services;

use App\Modules\Ateliers\Exceptions\DiagnosticIndisponibleException;
use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Diagnostic de réparation par photo : prépare le catalogue, appelle le microservice ia-diagnostic
 * (CLIP, en Python) et traduit sa réponse en réparations et en ateliers.
 * Aucune donnée personnelle n'est envoyée : la photo, la précision du client et le catalogue des services.
 */
class DiagnosticService
{
    /** Limites du microservice. */
    public const SERVICES_MAX = 200;

    private const NOM_MAX = 120;

    private const DESCRIPTION_MAX = 1000;

    public const ATELIERS_PAR_REPARATION = 3;

    /** Libellés affichés (clés renvoyées par le microservice). */
    private const VETEMENTS = [
        'jean' => 'Jean', 'pantalon' => 'Pantalon', 'chemise' => 'Chemise', 't-shirt' => 'T-shirt',
        'robe' => 'Robe', 'jupe' => 'Jupe', 'veste' => 'Veste', 'manteau' => 'Manteau',
        'pull' => 'Pull', 'chaussette' => 'Chaussette',
    ];

    private const DEFAUTS = [
        'trou' => 'Trou', 'déchirure' => 'Déchirure', 'fermeture cassée' => 'Fermeture cassée',
        'bouton manquant' => 'Bouton manquant', 'ourlet décousu' => 'Ourlet décousu',
        'couture défaite' => 'Couture défaite', 'maille filée' => 'Maille filée',
        'tache' => 'Tache', 'trop grand' => 'Taille à ajuster',
    ];

    /**
     * Défauts que le citoyen peut choisir lui-même (« Ce n'est pas ça ? ») : clé du microservice => libellé court.
     */
    public const CORRECTIONS = [
        'trou' => 'Trou', 'déchirure' => 'Déchirure', 'fermeture cassée' => 'Fermeture', 'bouton manquant' => 'Bouton',
        'ourlet décousu' => 'Ourlet', 'maille filée' => 'Maille', 'tache' => 'Tache', 'trop grand' => 'Taille',
    ];

    public function __construct(private AtelierService $ateliers, private RecommandationService $recommandation)
    {
    }

    /**
     * @param  string|null  $defaut  défaut choisi par le citoyen (clé de CORRECTIONS) : il remplace l'avis de la photo
     * @param  array{0: float, 1: float}|null  $position  [latitude, longitude] du citoyen, pour classer les ateliers par distance
     * @return array{
     *     vetement: ?string,
     *     defauts: list<string>,
     *     defaut_choisi: ?string,
     *     certain: bool,
     *     propositions: list<array{nom: string, prix_min: ?float, nb_ateliers: int, offres: list<array{atelier: Atelier, service: Service, score: float, distance_km: ?float, ouvert: bool, raisons: list<string>}>}>
     * }
     *
     * @throws DiagnosticIndisponibleException
     */
    public function diagnostiquer(UploadedFile $photo, ?string $texte = null, ?string $defaut = null, ?array $position = null): array
    {
        $defaut = array_key_exists((string) $defaut, self::CORRECTIONS) ? $defaut : null;

        $catalogue = $this->catalogue($this->ateliers->actifsAvecServices());

        if ($catalogue === []) {
            throw DiagnosticIndisponibleException::catalogueVide();
        }

        return $this->resultat($this->appeler($photo, $texte, $defaut, $catalogue), $catalogue, $defaut, $position);
    }

    /**
     * Regroupe les services de même nom : plusieurs ateliers proposent « Recoudre une déchirure ».
     * Le microservice classe des réparations ; on retrouve ensuite les ateliers qui les proposent.
     *
     * @param  Collection<int, Atelier>  $ateliers
     * @return array<string, array{nom: string, description: string, offres: list<array{atelier: Atelier, service: Service}>}>
     */
    public function catalogue(Collection $ateliers): array
    {
        $groupes = [];

        foreach ($ateliers as $atelier) {
            foreach ($atelier->services as $service) {
                $nom = trim((string) $service->nom);

                if ($nom === '') {
                    continue;
                }

                $cle = (string) Str::of($nom)->ascii()->lower()->squish();
                $groupes[$cle] ??= ['nom' => mb_substr($nom, 0, self::NOM_MAX), 'description' => '', 'offres' => []];

                if ($groupes[$cle]['description'] === '' && filled($service->description)) {
                    $groupes[$cle]['description'] = mb_substr(trim((string) $service->description), 0, self::DESCRIPTION_MAX);
                }

                $groupes[$cle]['offres'][] = ['atelier' => $atelier, 'service' => $service];
            }
        }

        // Au-delà de la limite du microservice, on garde les réparations proposées par le plus d'ateliers.
        uasort($groupes, fn (array $a, array $b) => count($b['offres']) <=> count($a['offres']));

        // Identifiants courts envoyés au microservice : s0, s1…
        $catalogue = [];
        foreach (array_values(array_slice($groupes, 0, self::SERVICES_MAX)) as $index => $groupe) {
            $catalogue["s{$index}"] = $groupe;
        }

        return $catalogue;
    }

    /**
     * @throws DiagnosticIndisponibleException
     */
    private function appeler(UploadedFile $photo, ?string $texte, ?string $defaut, array $catalogue): array
    {
        $config = config('ateliers_diagnostic');

        if (blank($config['jeton'] ?? null)) {
            Log::error('Diagnostic par photo : IA_DIAGNOSTIC_JETON n\'est pas défini dans .env.');

            throw DiagnosticIndisponibleException::indisponible();
        }

        $services = [];
        foreach ($catalogue as $id => $groupe) {
            $services[] = ['id' => $id, 'nom' => $groupe['nom'], 'description' => $groupe['description']];
        }

        $champs = ['services' => json_encode($services, JSON_UNESCAPED_UNICODE)];
        if (filled($texte)) {
            $champs['texte'] = $texte;
        }
        if ($defaut !== null) {
            $champs['defaut'] = $defaut;
        }

        try {
            $reponse = Http::baseUrl($config['url'])
                ->withToken($config['jeton'])
                ->acceptJson()
                ->connectTimeout($config['delai_connexion'])
                ->timeout($config['delai'])
                ->attach('photo', (string) file_get_contents($photo->getRealPath()), 'photo.'.$photo->extension())
                ->post('/diagnostic', $champs);
        } catch (ConnectionException $e) {
            Log::warning('Diagnostic par photo : service injoignable.', ['erreur' => $e->getMessage()]);

            throw DiagnosticIndisponibleException::indisponible();
        }

        // Le texte d'erreur du service n'est jamais affiché tel quel : messages propres à Laravel.
        $raison = match ($reponse->status()) {
            413 => 'elle ne doit pas dépasser 5 Mo.',
            415 => 'elle doit être au format JPG, PNG ou WebP.',
            422 => "le fichier n'est pas une image lisible.",
            default => null,
        };

        if ($raison !== null) {
            throw DiagnosticIndisponibleException::photoRefusee($raison);
        }

        $donnees = $reponse->json();

        if (! $reponse->successful() || ! is_array($donnees)
            || ! is_array($donnees['services'] ?? null) || ! is_array($donnees['defauts'] ?? null)) {
            Log::warning('Diagnostic par photo : réponse inattendue du service.', ['statut' => $reponse->status()]);

            throw DiagnosticIndisponibleException::indisponible();
        }

        return $donnees;
    }

    private function resultat(array $donnees, array $catalogue, ?string $defaut, ?array $position): array
    {
        $propositions = [];

        foreach ($donnees['services'] as $proposition) {
            $id = is_array($proposition) ? ($proposition['id'] ?? null) : null;

            // Un identifiant absent du catalogue envoyé est ignoré.
            if (! is_string($id) || ! isset($catalogue[$id])) {
                continue;
            }

            // Les ateliers qui proposent cette réparation sont classés et leur classement expliqué.
            $offres = $this->recommandation->classer($catalogue[$id]['offres'], $position);
            $prix = array_filter(array_map(fn (array $o) => $o['service']->prix_estime, $offres), 'is_numeric');

            $propositions[] = [
                'nom' => $catalogue[$id]['nom'],
                'prix_min' => $prix === [] ? null : (float) min($prix),
                'nb_ateliers' => count($offres),
                'offres' => array_slice($offres, 0, self::ATELIERS_PAR_REPARATION),
            ];
        }

        $vetement = $donnees['vetements'][0]['cle'] ?? null;

        return [
            'vetement' => is_string($vetement) ? (self::VETEMENTS[$vetement] ?? null) : null,
            'defauts' => array_values(array_filter(array_map(
                fn ($defaut) => is_array($defaut) && is_string($defaut['cle'] ?? null) ? (self::DEFAUTS[$defaut['cle']] ?? null) : null,
                array_slice($donnees['defauts'], 0, 2)
            ))),
            'defaut_choisi' => $defaut,
            'certain' => ($donnees['certain'] ?? false) === true,
            'propositions' => $propositions,
        ];
    }
}
