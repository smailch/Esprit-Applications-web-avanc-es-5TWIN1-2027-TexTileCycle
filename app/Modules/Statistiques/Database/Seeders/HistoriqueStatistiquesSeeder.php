<?php

namespace App\Modules\Statistiques\Database\Seeders;

use App\Modules\Statistiques\Models\ImpactEcologique;
use App\Modules\Statistiques\Models\Statistique;
use App\Modules\Statistiques\Services\PlatformMetricsService;
use Illuminate\Database\Seeder;

/**
 * Historique de démonstration : 24 mois consolidés AVANT la première activité réelle
 * (croissance + saisonnalité : réparations au printemps, dons en hiver).
 * Idempotent : ne touche qu'aux documents `source = seed`.
 */
class HistoriqueStatistiquesSeeder extends Seeder
{
    public const MOIS = 24;

    /** facteur saisonnier par mois [réparations, dons] */
    private const SAISONS = [
        1 => [0.9, 1.35], 2 => [0.95, 1.25], 3 => [1.25, 1.0], 4 => [1.35, 0.9],
        5 => [1.3, 0.85], 6 => [1.05, 0.8], 7 => [0.85, 0.75], 8 => [0.8, 0.8],
        9 => [1.0, 1.05], 10 => [1.0, 1.15], 11 => [0.95, 1.3], 12 => [0.85, 1.4],
    ];

    public function run(PlatformMetricsService $metrics): void
    {
        $premiereActivite = $metrics->premiereActivite() ?? now()->startOfMonth();

        // Avant la première activité réelle, aucune donnée réelle n'existe : on repart de zéro.
        foreach ([Statistique::class, ImpactEcologique::class] as $modele) {
            $modele::where('source', Statistique::SOURCE_SEED)->delete();
            $modele::where('periode', '<', $premiereActivite)->delete();
        }

        $fin = $premiereActivite->copy()->subMonth();
        $debut = $fin->copy()->subMonths(self::MOIS - 1);

        for ($i = 0, $mois = $debut->copy(); $mois->lte($fin); $i++, $mois->addMonth()) {
            [$fReparation, $fDon] = self::SAISONS[$mois->month];
            $croissance = 1 + $i * 0.04;

            $reparations = (int) round(14 * $croissance * $fReparation + random_int(-2, 2));
            $dons = (int) round(8 * $croissance * $fDon + random_int(-1, 1));

            Statistique::factory()
                ->periode($mois)
                ->create([
                    'nb_reparations' => $reparations,
                    'nb_dons' => $dons,
                    'nb_vetements' => $reparations + $dons + random_int(4, 10),
                    'nb_utilisateurs' => (int) round(6 * $croissance + random_int(0, 4)),
                    'nb_ateliers' => min(12, 3 + intdiv($i, 3)),
                ]);
        }

        $this->command?->info(self::MOIS." mois d'historique de démonstration créés ({$debut->format('m/Y')} → {$fin->format('m/Y')}).");
    }
}
