<?php

namespace App\Modules\Ateliers\Services;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use Carbon\CarbonInterface;

/**
 * Classe les ateliers qui proposent une même réparation et explique pourquoi.
 * Score sur 1 = 40 % note (moyenne bayésienne) + 35 % distance + 15 % prix + 10 % ouvert maintenant.
 * Sans position du citoyen, le poids de la distance est réparti sur les autres critères.
 * Aucune lecture en base : tout vient des modèles déjà chargés.
 */
class RecommandationService
{
    /** Note « a priori » et poids de cet a priori (en nombre d'avis) pour la moyenne bayésienne. */
    private const NOTE_A_PRIORI = 3.5;

    private const AVIS_A_PRIORI = 5;

    /** Distance à laquelle le score de distance vaut 0,5. */
    private const DISTANCE_MOITIE_KM = 5.0;

    private const POIDS = ['note' => 0.40, 'distance' => 0.35, 'prix' => 0.15, 'ouvert' => 0.10];

    public const RAISON_PROCHE = 'Le plus proche';

    public const RAISON_NOTE = 'Le mieux noté';

    public const RAISON_PRIX = 'Le moins cher';

    /**
     * @param  list<array{atelier: Atelier, service: Service}>  $offres
     * @param  array{0: float, 1: float}|null  $position  [latitude, longitude] du citoyen
     * @return list<array{atelier: Atelier, service: Service, score: float, distance_km: ?float, ouvert: bool, raisons: list<string>}>
     */
    public function classer(array $offres, ?array $position = null, ?CarbonInterface $moment = null): array
    {
        if ($offres === []) {
            return [];
        }

        $moment ??= Atelier::maintenant();
        $lignes = array_map(fn (array $offre) => $offre + [
            'note_bayesienne' => $this->noteBayesienne($offre['atelier']),
            'distance_km' => $this->distanceKm($offre['atelier'], $position),
            'prix' => is_numeric($offre['service']->prix_estime) ? (float) $offre['service']->prix_estime : null,
            'ouvert' => $offre['atelier']->estOuvert($moment),
        ], $offres);

        $prix = array_filter(array_column($lignes, 'prix'), fn ($p) => $p !== null && $p > 0);
        $prixMin = $prix === [] ? null : min($prix);
        $avecDistance = array_filter(array_column($lignes, 'distance_km'), fn ($d) => $d !== null) !== [];

        // Sans position, la distance ne compte pas : ses points sont répartis sur les autres critères.
        $poids = self::POIDS;
        if (! $avecDistance) {
            $reste = 1 - $poids['distance'];
            $poids = ['note' => $poids['note'] / $reste, 'distance' => 0.0, 'prix' => $poids['prix'] / $reste, 'ouvert' => $poids['ouvert'] / $reste];
        }

        foreach ($lignes as &$ligne) {
            // Note de 1 à 5 ramenée entre 0 et 1.
            $scoreNote = max(0.0, min(1.0, ($ligne['note_bayesienne'] - 1) / 4));
            $scoreDistance = $ligne['distance_km'] === null ? 0.0 : 1 / (1 + $ligne['distance_km'] / self::DISTANCE_MOITIE_KM);
            // Prix comparé au moins cher (rapport) : 8 TND contre 15 TND donne 0,53, pas 0 ; le prix départage plus qu'il ne décide.
            $scorePrix = match (true) {
                $ligne['prix'] === null || $prixMin === null => 0.0,
                $ligne['prix'] <= 0 => 1.0,
                default => $prixMin / $ligne['prix'],
            };

            $ligne['score'] = round($poids['note'] * $scoreNote + $poids['distance'] * $scoreDistance
                + $poids['prix'] * $scorePrix + $poids['ouvert'] * ($ligne['ouvert'] ? 1 : 0), 4);
        }
        unset($ligne);

        usort($lignes, fn (array $a, array $b) => [$b['score'], (string) $a['atelier']->nom] <=> [$a['score'], (string) $b['atelier']->nom]);

        return $this->ajouterRaisons($lignes, count($offres) > 1);
    }

    /**
     * Une raison n'est donnée que si elle distingue vraiment l'atelier (un seul « plus proche », etc.).
     * L'ouverture n'en fait pas partie : la vue l'affiche déjà avec sa pastille « Ouvert maintenant / Fermé ».
     */
    private function ajouterRaisons(array $lignes, bool $plusieurs): array
    {
        $plusProche = $this->meilleur($lignes, fn (array $l) => $l['distance_km'] === null ? null : -$l['distance_km']);
        $mieuxNote = $this->meilleur($lignes, fn (array $l) => (int) $l['atelier']->nb_avis > 0 ? $l['note_bayesienne'] : null);
        $moinsCher = $this->meilleur($lignes, fn (array $l) => $l['prix'] === null ? null : -$l['prix']);

        foreach ($lignes as $index => &$ligne) {
            $raisons = [];
            if ($plusieurs && $index === $plusProche) {
                $raisons[] = self::RAISON_PROCHE;
            }
            if ($plusieurs && $index === $mieuxNote) {
                $raisons[] = self::RAISON_NOTE;
            }
            if ($plusieurs && $index === $moinsCher) {
                $raisons[] = self::RAISON_PRIX;
            }

            $ligne = [
                'atelier' => $ligne['atelier'],
                'service' => $ligne['service'],
                'score' => $ligne['score'],
                'distance_km' => $ligne['distance_km'],
                'ouvert' => $ligne['ouvert'],
                'raisons' => $raisons,
            ];
        }
        unset($ligne);

        return $lignes;
    }

    /**
     * Index de la ligne qui a la plus grande valeur ; null si aucune valeur ou si plusieurs lignes sont à égalité.
     */
    private function meilleur(array $lignes, callable $valeur): ?int
    {
        $valeurs = array_filter(array_map($valeur, $lignes), fn ($v) => $v !== null);
        if ($valeurs === []) {
            return null;
        }

        $max = max($valeurs);
        $indexes = array_keys(array_filter($valeurs, fn ($v) => $v == $max));

        return count($indexes) === 1 ? $indexes[0] : null;
    }

    /**
     * Moyenne bayésienne : un 5/5 sur un seul avis ne passe pas devant un 4,8/5 sur 50 avis.
     */
    public function noteBayesienne(Atelier $atelier): float
    {
        $avis = max(0, (int) $atelier->nb_avis);
        $note = (float) $atelier->note_moyenne;

        return (self::NOTE_A_PRIORI * self::AVIS_A_PRIORI + $note * $avis) / (self::AVIS_A_PRIORI + $avis);
    }

    private function distanceKm(Atelier $atelier, ?array $position): ?float
    {
        if ($position === null || ! is_numeric($atelier->latitude) || ! is_numeric($atelier->longitude)) {
            return null;
        }

        return round(AtelierService::distanceKm($position[0], $position[1], (float) $atelier->latitude, (float) $atelier->longitude), 2);
    }
}
