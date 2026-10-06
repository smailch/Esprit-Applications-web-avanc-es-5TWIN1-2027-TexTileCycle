<?php

namespace App\Modules\Statistiques\Services;

use App\Modules\Statistiques\Models\ImpactEcologique;
use App\Modules\Statistiques\Models\Statistique;
use Carbon\Carbon;

/**
 * Enregistre les agrégats mensuels dans les collections
 * `statistiques` et `impact_ecologique` (une ligne par mois, idempotent).
 */
class ConsolidationService
{
    public function __construct(
        private PlatformMetricsService $metrics
    ) {
    }

    /**
     * @return int nombre de mois consolidés
     */
    public function consolider(int $mois = 1): int
    {
        $debut = now()->startOfMonth()->subMonths(max(1, $mois) - 1);

        for ($periode = $debut->copy(); $periode->lte(now()); $periode->addMonth()) {
            $this->consoliderMois($periode->copy());
        }

        return max(1, $mois);
    }

    public function consoliderMois(Carbon $periode): void
    {
        $periode = $periode->copy()->startOfMonth();
        $donnees = $this->metrics->periode($periode);

        Statistique::updateOrCreate(['periode' => $periode], $donnees['statistique']);
        ImpactEcologique::updateOrCreate(['periode' => $periode], $donnees['impact']);
    }
}
