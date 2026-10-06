<?php

namespace App\Modules\Statistiques\Services;

use App\Modules\Statistiques\Models\ImpactEcologique;
use App\Modules\Statistiques\Models\Statistique;
use Carbon\Carbon;

/**
 * Enregistre les agrégats mensuels dans les collections
 * `statistiques` et `impact_ecologique` (une ligne par mois, idempotent).
 *
 * Les mois antérieurs à la première activité réelle ne sont jamais réécrits :
 * ils peuvent contenir l'historique de démonstration du seeder.
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
        $premiereActivite = $this->metrics->premiereActivite() ?? now()->startOfMonth();

        if ($debut->lt($premiereActivite)) {
            $debut = $premiereActivite->copy();
        }

        $nb = 0;
        for ($periode = $debut->copy(); $periode->lte(now()); $periode->addMonth()) {
            $this->consoliderMois($periode->copy());
            $nb++;
        }

        return $nb;
    }

    public function consoliderMois(Carbon $periode): void
    {
        $periode = $periode->copy()->startOfMonth();
        $donnees = $this->metrics->periode($periode);
        $source = ['source' => Statistique::SOURCE_CONSOLIDATION];

        Statistique::updateOrCreate(['periode' => $periode], $donnees['statistique'] + $source);
        ImpactEcologique::updateOrCreate(['periode' => $periode], $donnees['impact'] + $source);
    }
}
