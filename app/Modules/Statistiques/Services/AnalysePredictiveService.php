<?php

namespace App\Modules\Statistiques\Services;

use Carbon\Carbon;

/**
 * IA du module 5 — analyse prédictive et détection d'anomalies.
 *
 * Techniques (sans dépendance externe, explicables) :
 *  - prévision : régression linéaire (moindres carrés) corrigée d'un indice saisonnier ;
 *  - tendance : variation des 3 derniers mois complets vs les 3 précédents ;
 *  - anomalie : z-score du dernier mois complet par rapport à l'historique ;
 *  - saisonnalité : indice moyen par saison quand l'historique couvre au moins 12 mois ;
 *  - partenaires : chute d'activité RDV d'un atelier (30 j vs moyenne des 3 mois précédents).
 */
class AnalysePredictiveService
{
    public const HORIZON = 3;

    private const SEUIL_Z = 2.0;

    private const SEUIL_TENDANCE = 0.15;

    private const SEUIL_SAISON = 0.10;

    private const SAISONS = [
        'hiver' => [12, 1, 2],
        'printemps' => [3, 4, 5],
        'été' => [6, 7, 8],
        'automne' => [9, 10, 11],
    ];

    private const INDICATEURS = [
        'reparations' => 'réparations',
        'dons' => 'dons validés',
        'vetements' => 'vêtements déclarés',
        'inscriptions' => 'inscriptions',
        'rendez_vous' => 'rendez-vous',
    ];

    /**
     * @param  array{mois: list<string>, series: array<string, list<float|int>>}  $historique  séries mensuelles (mois courant en dernier)
     * @param  list<array{nom: string, recent: int, moyenne: float}>  $ateliers
     */
    public function analyser(array $historique, array $ateliers = []): array
    {
        $mois = $historique['mois'];
        $insights = [];
        $previsions = [];

        foreach (self::INDICATEURS as $cle => $libelle) {
            // Le mois courant est incomplet : on raisonne sur les mois terminés.
            $serie = array_slice($historique['series'][$cle] ?? [], 0, -1);
            $moisComplets = array_slice($mois, 0, -1);

            if (! $this->suffisant($serie)) {
                continue;
            }

            $insights[] = $this->tendance($libelle, $serie);
            $insights[] = $this->anomalie($libelle, $serie, end($moisComplets));
            $insights[] = $this->saisonnalite($libelle, $serie, $moisComplets);
        }

        foreach (['reparations', 'dons', 'sauves', 'co2'] as $cle) {
            $serie = array_slice($historique['series'][$cle] ?? [], 0, -1);
            $previsions[$cle] = $this->prevoir($serie, array_slice($mois, 0, -1));
        }

        foreach ($ateliers as $atelier) {
            $insights[] = $this->baisseAtelier($atelier);
        }

        $insights = array_values(array_filter($insights));

        if ($insights === []) {
            $insights[] = [
                'niveau' => 'info',
                'icone' => 'database',
                'titre' => 'Historique encore insuffisant',
                'message' => "L'IA a besoin d'au moins 3 mois d'activité pour détecter des tendances fiables. Les analyses s'enrichiront automatiquement avec les nouvelles données.",
            ];
        }

        usort($insights, fn ($a, $b) => $this->priorite($b['niveau']) <=> $this->priorite($a['niveau']));

        return [
            'insights' => $insights,
            'previsions' => $previsions,
            // Prévisions à partir du dernier mois complet : mois courant + 2 suivants.
            'labels_prevision' => $this->moisSuivants($mois[count($mois) - 2]),
        ];
    }

    /**
     * Prévision sur HORIZON mois : tendance linéaire × indice saisonnier.
     *
     * @return array{valeurs: list<float>, confiance: string, r2: float, pente: float}
     */
    public function prevoir(array $serie, array $mois): array
    {
        if (! $this->suffisant($serie)) {
            return ['valeurs' => array_fill(0, self::HORIZON, 0.0), 'confiance' => 'insuffisante', 'r2' => 0.0, 'pente' => 0.0];
        }

        [$pente, $origine, $r2] = $this->regression($serie);
        $indices = count($serie) >= 12 ? $this->indicesSaisonniers($serie, $mois) : [];
        $n = count($serie);
        $suivants = $this->moisSuivants(end($mois), 'Y-m');

        $valeurs = [];
        for ($h = 0; $h < self::HORIZON; $h++) {
            $base = $origine + $pente * ($n + $h);
            $saison = $this->saisonDe((int) substr($suivants[$h], 5, 2));
            $valeurs[] = round(max(0, $base * (1 + ($indices[$saison] ?? 0))), 1);
        }

        $confiance = match (true) {
            $n >= 12 && $r2 >= 0.6 => 'élevée',
            $n >= 6 && $r2 >= 0.3 => 'moyenne',
            default => 'faible',
        };

        return ['valeurs' => $valeurs, 'confiance' => $confiance, 'r2' => round($r2, 2), 'pente' => round($pente, 2)];
    }

    /**
     * Régression linéaire y = a·x + b par moindres carrés.
     *
     * @return array{0: float, 1: float, 2: float} [pente, origine, R²]
     */
    public function regression(array $y): array
    {
        $n = count($y);
        $x = range(0, $n - 1);
        $moyX = array_sum($x) / $n;
        $moyY = array_sum($y) / $n;

        $cov = $varX = $varY = 0.0;
        foreach ($y as $i => $yi) {
            $cov += ($x[$i] - $moyX) * ($yi - $moyY);
            $varX += ($x[$i] - $moyX) ** 2;
            $varY += ($yi - $moyY) ** 2;
        }

        $pente = $varX > 0 ? $cov / $varX : 0.0;
        $r2 = ($varX > 0 && $varY > 0) ? ($cov ** 2) / ($varX * $varY) : 0.0;

        return [$pente, $moyY - $pente * $moyX, $r2];
    }

    private function tendance(string $libelle, array $serie): ?array
    {
        if (count($serie) < 6) {
            return null;
        }

        $recent = array_sum(array_slice($serie, -3));
        $avant = array_sum(array_slice($serie, -6, 3));

        if ($avant <= 0) {
            return null;
        }

        $variation = ($recent - $avant) / $avant;

        if (abs($variation) < self::SEUIL_TENDANCE) {
            return null;
        }

        $pct = $this->pourcent($variation);

        return $variation > 0
            ? ['niveau' => 'success', 'icone' => 'trending-up', 'titre' => "Hausse des {$libelle}", 'message' => "{$pct} de {$libelle} sur les 3 derniers mois par rapport au trimestre précédent."]
            : ['niveau' => 'warning', 'icone' => 'trending-down', 'titre' => "Baisse des {$libelle}", 'message' => "{$pct} de {$libelle} sur les 3 derniers mois par rapport au trimestre précédent."];
    }

    private function anomalie(string $libelle, array $serie, string $mois): ?array
    {
        if (count($serie) < 4) {
            return null;
        }

        $dernier = end($serie);
        $historique = array_slice($serie, 0, -1);
        $moyenne = array_sum($historique) / count($historique);
        $ecart = sqrt(array_sum(array_map(fn ($v) => ($v - $moyenne) ** 2, $historique)) / count($historique));

        if ($ecart < 0.5) {
            return null;
        }

        $z = ($dernier - $moyenne) / $ecart;

        if (abs($z) < self::SEUIL_Z) {
            return null;
        }

        $nomMois = Carbon::createFromFormat('Y-m-d', $mois.'-01')->locale('fr')->isoFormat('MMMM YYYY');
        $sens = $z > 0 ? 'inhabituellement élevé' : 'inhabituellement bas';

        return [
            'niveau' => $z > 0 ? 'info' : 'danger',
            'icone' => 'radar',
            'titre' => 'Anomalie détectée',
            'message' => sprintf(
                'Nombre de %s %s en %s : %s contre %s en moyenne (z-score %+.1f).',
                $libelle, $sens, $nomMois, $this->nombre($dernier), $this->nombre($moyenne), $z
            ),
        ];
    }

    private function saisonnalite(string $libelle, array $serie, array $mois): ?array
    {
        if (count($serie) < 12) {
            return null;
        }

        $indices = $this->indicesSaisonniers($serie, $mois);
        arsort($indices);
        $saison = array_key_first($indices);

        if ($indices[$saison] < self::SEUIL_SAISON) {
            return null;
        }

        return [
            'niveau' => 'info',
            'icone' => 'sun-snow',
            'titre' => 'Tendance saisonnière',
            'message' => sprintf('%s de %s %s par rapport à la moyenne annuelle.', $this->pourcent($indices[$saison]), $libelle, $saison === 'printemps' ? 'au printemps' : 'en '.$saison),
        ];
    }

    /**
     * @param  array{nom: string, recent: int, moyenne: float}  $atelier
     */
    private function baisseAtelier(array $atelier): ?array
    {
        if ($atelier['moyenne'] < 2 || $atelier['recent'] >= $atelier['moyenne'] * 0.5) {
            return null;
        }

        $variation = ($atelier['recent'] - $atelier['moyenne']) / $atelier['moyenne'];

        return [
            'niveau' => 'danger',
            'icone' => 'store',
            'titre' => 'Baisse d\'activité partenaire',
            'message' => sprintf(
                '%s : %d RDV sur les 30 derniers jours contre %s par mois habituellement (%s).',
                $atelier['nom'], $atelier['recent'], $this->nombre($atelier['moyenne']), $this->pourcent($variation)
            ),
        ];
    }

    /**
     * Écart relatif moyen de chaque saison par rapport à la moyenne globale.
     *
     * @return array<string, float>
     */
    private function indicesSaisonniers(array $serie, array $mois): array
    {
        $moyenne = array_sum($serie) / max(1, count($serie));

        if ($moyenne <= 0) {
            return [];
        }

        $parSaison = [];
        foreach ($serie as $i => $valeur) {
            $parSaison[$this->saisonDe((int) substr($mois[$i], 5, 2))][] = $valeur;
        }

        return array_map(fn ($valeurs) => (array_sum($valeurs) / count($valeurs)) / $moyenne - 1, $parSaison);
    }

    private function suffisant(array $serie): bool
    {
        return count(array_filter($serie, fn ($v) => $v > 0)) >= 3;
    }

    private function saisonDe(int $mois): string
    {
        foreach (self::SAISONS as $saison => $liste) {
            if (in_array($mois, $liste, true)) {
                return $saison;
            }
        }

        return 'hiver';
    }

    private function moisSuivants(string $dernierMois, ?string $format = null): array
    {
        $date = Carbon::createFromFormat('Y-m-d', $dernierMois.'-01')->locale('fr');

        return array_map(
            fn (int $h) => $format ? $date->copy()->addMonths($h)->format($format) : $date->copy()->addMonths($h)->isoFormat('MMM YY'),
            range(1, self::HORIZON)
        );
    }

    private function priorite(string $niveau): int
    {
        return ['danger' => 3, 'warning' => 2, 'success' => 1][$niveau] ?? 0;
    }

    private function pourcent(float $ratio): string
    {
        return sprintf('%+d %%', (int) round($ratio * 100));
    }

    private function nombre(float $valeur): string
    {
        return rtrim(rtrim(number_format($valeur, 1, ',', ' '), '0'), ',');
    }
}
