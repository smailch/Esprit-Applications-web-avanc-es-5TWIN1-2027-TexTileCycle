<?php

namespace App\Modules\Statistiques\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use App\Modules\Statistiques\Models\Statistique;
use App\Modules\Statistiques\Services\AnalysePredictiveService;
use App\Modules\Statistiques\Services\ConsolidationService;
use App\Modules\Statistiques\Services\ImpactCalculator;
use App\Modules\Statistiques\Services\PlatformMetricsService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatistiqueController extends Controller
{
    use RendersBackOffice;

    public function __construct(
        private PlatformMetricsService $metrics,
        private AnalysePredictiveService $ia
    ) {
    }

    public function index()
    {
        // 24 mois pour l'IA (saisonnalité), 12 mois affichés dans les graphiques.
        $historiqueIa = $this->metrics->seriesMensuelles(24);

        return $this->backView('back.statistiques', [
            'pageTitle' => 'Statistiques & impact',
            'kpis' => $this->metrics->overview(),
            'series' => $this->metrics->derniersMois($historiqueIa, 12),
            'analyse' => $this->ia->analyser($historiqueIa, $this->metrics->activiteAteliers()),
            'repartition' => $this->metrics->repartitionVetements(),
            'topTypes' => $this->metrics->topTypes(),
            'historique' => $this->historique(),
            'coefficients' => ImpactCalculator::COEFFICIENTS,
            'coefficientDefaut' => ImpactCalculator::DEFAUT,
            'facteurs' => ImpactCalculator::FACTEURS,
        ]);
    }

    public function consolider(Request $request, ConsolidationService $consolidation)
    {
        $data = $request->validate(['mois' => ['nullable', 'integer', 'min:1', 'max:24']]);
        $nb = $consolidation->consolider($data['mois'] ?? 12);

        return back()->with('success', "{$nb} mois consolidés dans l'historique.");
    }

    public function export(): StreamedResponse
    {
        $lignes = $this->historique();

        return response()->streamDownload(function () use ($lignes) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Période', 'Vêtements', 'Réparations', 'Dons', 'Nouveaux inscrits', 'Ateliers actifs', 'Vêtements sauvés', 'CO2 évité (kg)', 'Eau économisée (L)'], ';');
            foreach ($lignes as $l) {
                fputcsv($out, [$l['periode']->format('Y-m'), $l['nb_vetements'], $l['nb_reparations'], $l['nb_dons'], $l['nb_utilisateurs'], $l['nb_ateliers'], $l['vetements_sauves'], $l['co2_evite_kg'], $l['eau_economisee_litres']], ';');
            }
            fclose($out);
        }, 'textilecycle-statistiques-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Historique consolidé : chaque statistique avec son impact de la même période (relation 1:1).
     */
    private function historique(): array
    {
        return Statistique::with('impact')->orderBy('periode', 'desc')->get()->map(fn (Statistique $s) => [
            'periode' => $s->periode,
            'demo' => $s->estDemo(),
            'nb_vetements' => $s->nb_vetements,
            'nb_reparations' => $s->nb_reparations,
            'nb_dons' => $s->nb_dons,
            'nb_utilisateurs' => $s->nb_utilisateurs,
            'nb_ateliers' => $s->nb_ateliers,
            'vetements_sauves' => $s->impact?->vetements_sauves ?? 0,
            'co2_evite_kg' => $s->impact?->co2_evite_kg ?? 0,
            'eau_economisee_litres' => $s->impact?->eau_economisee_litres ?? 0,
        ])->all();
    }
}
