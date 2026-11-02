<?php

namespace App\Modules\Statistiques\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Core\Support\GeminiJson;
use App\Modules\Messagerie\Models\Message;
use App\Modules\Signalements\Models\Signalement;
use App\Modules\Statistiques\Models\RapportImpact;
use App\Modules\Statistiques\Models\Statistique;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Rapport d'impact mensuel rédigé par l'IA.
 *
 *  1. Les FAITS sont calculés par la plateforme (statistiques consolidées, impact écologique,
 *     modération, analyse prédictive) : l'IA ne calcule rien.
 *  2. Gemini RÉDIGE un rapport structuré à partir de ces seuls faits.
 *  3. Garde-fou : chaque nombre du texte rédigé est comparé aux faits ; un chiffre inventé
 *     fait rejeter la rédaction IA au profit d'une rédaction locale (modèle de texte).
 */
class RapportImpactService
{
    /** Ordres de grandeur pour rendre l'impact parlant (ADEME : voiture ≈ 0,12 kg CO₂/km ; douche ≈ 60 L). */
    private const KG_CO2_PAR_KM = 0.12;

    private const LITRES_PAR_DOUCHE = 60;

    public function __construct(
        private PlatformMetricsService $metrics,
        private AnalysePredictiveService $analyse,
        private GeminiJson $ia
    ) {
    }

    /**
     * Mois proposés : du premier mois avec des statistiques jusqu'au mois courant.
     *
     * @return array<string, string> "YYYY-MM" => « octobre 2026 »
     */
    public function moisDisponibles(): array
    {
        $premier = Statistique::orderBy('periode')->first()?->periode ?? now();
        $mois = [];

        for ($m = now()->startOfMonth(); $m->gte($premier->copy()->startOfMonth()); $m->subMonth()) {
            $mois[$m->format('Y-m')] = ucfirst($m->locale('fr')->isoFormat('MMMM YYYY'));
        }

        return $mois;
    }

    public function generer(Carbon $mois, User $admin): RapportImpact
    {
        $faits = $this->faits($mois);
        $redaction = $this->redigerIa($faits);
        $rejetes = $redaction ? $this->chiffresNonVerifies($redaction, $faits) : [];

        $source = 'gemini';
        if (! $redaction || $rejetes) {
            $redaction = $this->redigerLocal($faits);
            $source = 'local';
        }

        return RapportImpact::updateOrCreate(['mois' => $faits['mois']], [
            'faits' => $faits,
            'contenu' => $redaction,
            'source' => $source,
            'chiffres_rejetes' => $rejetes,
            'genere_par' => (string) $admin->getKey(),
        ]);
    }

    /**
     * Faits chiffrés du mois (et comparaison avec le mois précédent).
     */
    public function faits(Carbon $mois): array
    {
        $mois = $mois->copy()->startOfMonth();
        $actuel = $this->donneesMois($mois);
        $precedent = $this->donneesMois($mois->copy()->subMonth());
        $variation = fn (string $cle) => $precedent[$cle] > 0
            ? (int) round(100 * ($actuel[$cle] - $precedent[$cle]) / $precedent[$cle])
            : null;

        $fin = $mois->copy()->endOfMonth();
        $signalements = Signalement::whereBetween('created_at', [$mois, $fin])->get(['statut', 'source']);

        $faits = [
            'mois' => $mois->format('Y-m'),
            'mois_libelle' => $mois->locale('fr')->isoFormat('MMMM YYYY'),
            'mois_en_cours' => $mois->isSameMonth(now()),
            'donnees_demo' => $actuel['demo'],
            'activite' => [
                'vetements_declares' => $actuel['nb_vetements'],
                'reparations' => $actuel['nb_reparations'],
                'dons' => $actuel['nb_dons'],
                'nouveaux_inscrits' => $actuel['nb_utilisateurs'],
                'ateliers_actifs' => $actuel['nb_ateliers'],
            ],
            'impact' => [
                'vetements_sauves' => $actuel['vetements_sauves'],
                'co2_evite_kg' => round($actuel['co2'], 1),
                'eau_economisee_litres' => (int) round($actuel['eau']),
                'equivalent_km_voiture' => (int) round($actuel['co2'] / self::KG_CO2_PAR_KM),
                'equivalent_douches' => (int) round($actuel['eau'] / self::LITRES_PAR_DOUCHE),
            ],
            'variation_vs_mois_precedent_pourcent' => array_filter([
                'reparations' => $variation('nb_reparations'),
                'dons' => $variation('nb_dons'),
                'vetements_sauves' => $variation('vetements_sauves'),
                'co2_evite' => $variation('co2'),
            ], fn ($v) => $v !== null),
            'moderation' => [
                'signalements_recus' => $signalements->count(),
                'signalements_traites' => $signalements->where('statut', Signalement::STATUT_TRAITE)->count(),
                'signalements_rejetes' => $signalements->where('statut', Signalement::STATUT_REJETE)->count(),
                'messages_bloques_par_ia' => Message::where('bloque', true)->whereBetween('created_at', [$mois, $fin])->count(),
            ],
        ];

        // Tendances et prévisions de l'IA prédictive (calculées par AnalysePredictiveService).
        if ($mois->gte(now()->startOfMonth()->subMonth())) {
            $analyse = $this->analyse->analyser($this->metrics->seriesMensuelles(24));
            $faits['analyse_predictive'] = array_map(
                fn ($i) => $i['titre'].' : '.$i['message'],
                array_slice($analyse['insights'], 0, 3)
            );
        }

        return $faits;
    }

    /**
     * @return array{titre: string, resume: string, faits_marquants: list<string>, impact: string, moderation: string, recommandations: list<string>}|null
     */
    private function redigerIa(array $faits): ?array
    {
        $json = json_encode($faits, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $reponse = $this->ia->demander(<<<PROMPT
Tu rédiges le rapport d'impact mensuel de TexTileCycle, plateforme tunisienne d'économie circulaire
textile (réparation, don et recyclage de vêtements). Public : l'équipe et les partenaires.

RÈGLES STRICTES :
- Utilise UNIQUEMENT les chiffres présents dans les données ci-dessous, sans en calculer de nouveaux,
  sans arrondir différemment et sans inventer de pourcentage.
- Si "donnees_demo" est vrai, précise qu'il s'agit de données de démonstration.
- Si "mois_en_cours" est vrai, précise que le mois n'est pas terminé.
- Ton positif mais factuel, en français, phrases courtes.

Données :
{$json}

Réponds UNIQUEMENT avec un JSON :
{
  "titre": "titre accrocheur (max 70 caractères)",
  "resume": "2 à 3 phrases de synthèse",
  "faits_marquants": ["3 à 5 points courts"],
  "impact": "un paragraphe sur l'impact écologique avec les équivalences fournies",
  "moderation": "une ou deux phrases sur la modération et la sécurité de la communauté",
  "recommandations": ["2 ou 3 actions concrètes pour le mois suivant"]
}
PROMPT, 0.4);

        if (! $reponse || empty($reponse['titre']) || empty($reponse['resume'])) {
            return null;
        }

        return [
            'titre' => Str::limit(trim((string) $reponse['titre']), 90),
            'resume' => trim((string) $reponse['resume']),
            'faits_marquants' => array_values(array_slice(array_filter(array_map('trim', (array) ($reponse['faits_marquants'] ?? [])), 'strlen'), 0, 5)),
            'impact' => trim((string) ($reponse['impact'] ?? '')),
            'moderation' => trim((string) ($reponse['moderation'] ?? '')),
            'recommandations' => array_values(array_slice(array_filter(array_map('trim', (array) ($reponse['recommandations'] ?? [])), 'strlen'), 0, 3)),
        ];
    }

    /**
     * Garde-fou anti-hallucination : nombres du texte absents des faits.
     *
     * @return list<string>
     */
    public function chiffresNonVerifies(array $redaction, array $faits): array
    {
        $autorises = [];
        $autoriser = function (float $v) use (&$autorises) {
            foreach ([$v, abs($v), round($v), round($v, 1)] as $x) {
                $autorises[(string) (float) $x] = true;
            }
        };

        array_walk_recursive($faits, function ($valeur) use ($autoriser) {
            if (is_int($valeur) || is_float($valeur)) {
                $autoriser((float) $valeur);
            } elseif (is_string($valeur)) {
                // Chiffres cités dans les phrases de l'analyse prédictive (« +35 % au printemps »).
                preg_match_all('/\d+(?:[.,]\d+)?/', $valeur, $nombres);
                foreach ($nombres[0] as $n) {
                    $autoriser((float) str_replace(',', '.', $n));
                }
            }
        });

        $texte = implode(' ', array_map(fn ($v) => is_array($v) ? implode(' ', $v) : (string) $v, $redaction));
        preg_match_all('/\d+(?:[  .]\d{3})*(?:[.,]\d+)?/u', $texte, $trouves);

        $rejetes = [];
        foreach ($trouves[0] as $brut) {
            $nombre = (float) str_replace([' ', ' ', ','], ['', '', '.'], preg_replace('/[.](?=\d{3}\b)/', '', $brut));

            // Tolérés : petits nombres (« 3 actions », « 2 phrases ») et années.
            if ($nombre <= 5 || ($nombre >= 2000 && $nombre <= 2100 && floor($nombre) == $nombre)) {
                continue;
            }

            if (! isset($autorises[(string) $nombre])) {
                $rejetes[] = $brut;
            }
        }

        return array_values(array_unique($rejetes));
    }

    /**
     * Rédaction de secours, sans IA, à partir des mêmes faits.
     */
    public function redigerLocal(array $faits): array
    {
        $f = fn ($n, $d = 0) => number_format($n, $d, ',', ' ');
        $a = $faits['activite'];
        $i = $faits['impact'];
        $m = $faits['moderation'];
        $var = $faits['variation_vs_mois_precedent_pourcent'];
        $mois = $faits['mois_libelle'];
        $evolution = isset($var['vetements_sauves'])
            ? ' ('.($var['vetements_sauves'] >= 0 ? '+' : '').$var['vetements_sauves'].' % par rapport au mois précédent)'
            : '';

        return [
            'titre' => ucfirst($mois).' : '.$f($i['vetements_sauves']).' vêtements ont trouvé une seconde vie',
            'resume' => ($faits['mois_en_cours'] ? 'Bilan provisoire, le mois n\'étant pas terminé. ' : '')
                .($faits['donnees_demo'] ? 'Données de démonstration. ' : '')
                ."En {$mois}, la communauté TexTileCycle a sauvé {$f($i['vetements_sauves'])} vêtements{$evolution}, "
                ."grâce à {$f($a['reparations'])} réparations et {$f($a['dons'])} dons.",
            'faits_marquants' => array_values(array_filter([
                $f($a['vetements_declares']).' vêtements déclarés sur la plateforme.',
                $f($a['reparations']).' réparations réalisées'.(isset($var['reparations']) ? ' ('.($var['reparations'] >= 0 ? '+' : '').$var['reparations'].' %)' : '').'.',
                $f($a['dons']).' dons acceptés par les associations'.(isset($var['dons']) ? ' ('.($var['dons'] >= 0 ? '+' : '').$var['dons'].' %)' : '').'.',
                $a['nouveaux_inscrits'] ? $f($a['nouveaux_inscrits']).' nouveaux inscrits.' : null,
                $f($a['ateliers_actifs']).' ateliers partenaires actifs.',
            ])),
            'impact' => "Ces vêtements sauvés représentent {$f($i['co2_evite_kg'], 1)} kg de CO₂ évités, soit environ "
                ."{$f($i['equivalent_km_voiture'])} km en voiture, et {$f($i['eau_economisee_litres'])} litres d'eau économisés, "
                ."l'équivalent de {$f($i['equivalent_douches'])} douches.",
            'moderation' => $m['signalements_recus']
                ? "{$f($m['signalements_recus'])} signalement(s) reçu(s) : {$f($m['signalements_traites'])} traité(s), {$f($m['signalements_rejetes'])} rejeté(s)."
                    .($m['messages_bloques_par_ia'] ? " La modération IA a bloqué {$f($m['messages_bloques_par_ia'])} message(s)." : '')
                : 'Aucun signalement ce mois-ci : la communauté est restée bienveillante.',
            'recommandations' => [
                'Mettre en avant les ateliers les plus actifs pour encourager la réparation.',
                'Relayer les besoins des associations pour augmenter les dons.',
                'Traiter rapidement les signalements en attente.',
            ],
        ];
    }

    /**
     * Données d'un mois : historique consolidé si disponible (y compris démo), sinon calcul en direct.
     */
    private function donneesMois(Carbon $mois): array
    {
        $stat = Statistique::with('impact')->where('periode', $mois->copy()->startOfMonth())->first();

        if ($stat && ! $mois->isSameMonth(now())) {
            return [
                'nb_vetements' => (int) $stat->nb_vetements,
                'nb_reparations' => (int) $stat->nb_reparations,
                'nb_dons' => (int) $stat->nb_dons,
                'nb_utilisateurs' => (int) $stat->nb_utilisateurs,
                'nb_ateliers' => (int) $stat->nb_ateliers,
                'vetements_sauves' => (int) ($stat->impact?->vetements_sauves ?? 0),
                'co2' => (float) ($stat->impact?->co2_evite_kg ?? 0),
                'eau' => (float) ($stat->impact?->eau_economisee_litres ?? 0),
                'demo' => $stat->estDemo(),
            ];
        }

        $live = $this->metrics->periode($mois);

        return $live['statistique'] + [
            'vetements_sauves' => $live['impact']['vetements_sauves'],
            'co2' => $live['impact']['co2_evite_kg'],
            'eau' => $live['impact']['eau_economisee_litres'],
            'demo' => false,
        ];
    }
}
