<?php

namespace App\Modules\Statistiques\Services;

use App\Modules\Core\Support\MongoCollections;
use App\Modules\Statistiques\Models\Statistique;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use MongoDB\BSON\UTCDateTime;

/**
 * Collecte des indicateurs de la plateforme à partir des collections
 * des différents modules (vetements, rendez_vous, dons, users, ateliers…).
 */
class PlatformMetricsService
{
    /** statut vêtement (module 1, champ `status`) => type de valorisation */
    private const VALORISATIONS_VETEMENT = [
        'repare' => 'reparation',
        'donne' => 'don',
        'recycle' => 'recyclage',
    ];

    private ?Collection $evenements = null;

    public function __construct(
        private ImpactCalculator $impact
    ) {
    }

    /**
     * Indicateurs globaux en temps réel.
     */
    public function overview(): array
    {
        $evenements = $this->evenementsValorisation();
        $impact = $this->impact->total($evenements);
        $nbVetements = $this->count('vetements');
        $debutMois = now()->startOfMonth();

        return [
            'nb_utilisateurs' => $this->count('users'),
            'nb_inscrits_mois' => $this->count('users', ['created_at' => ['$gte' => new UTCDateTime($debutMois)]]),
            'nb_vetements' => $nbVetements,
            'nb_reparations' => $evenements->where('kind', 'reparation')->count(),
            'nb_dons' => $this->count('dons', ['statut' => 'accepte']),
            'nb_dons_en_attente' => $this->count('dons', ['statut' => 'en_attente']),
            'nb_rdv' => $this->count('rendez_vous'),
            'nb_ateliers' => $this->count('ateliers', ['statut' => 'actif']),
            'nb_associations' => $this->count('associations', ['statut' => 'actif']),
            'nb_partenaires_en_attente' => $this->count('ateliers', ['statut' => 'en_attente'])
                + $this->count('associations', ['statut' => 'en_attente']),
            'nb_signalements_en_attente' => $this->count('signalements', ['statut' => 'en_attente']),
            'vetements_sauves' => $impact['vetements'],
            'co2_evite_kg' => round($impact['co2'], 1),
            'eau_economisee_litres' => round($impact['eau']),
            'taux_valorisation' => $nbVetements > 0 ? round(100 * $impact['vetements'] / $nbVetements) : 0,
        ];
    }

    /**
     * Séries mensuelles sur les $mois derniers mois (mois courant inclus).
     *
     * @return array{labels: list<string>, mois: list<string>, series: array<string, list<float|int>>}
     */
    public function seriesMensuelles(int $mois = 12): array
    {
        $debut = now()->startOfMonth()->subMonths($mois - 1);
        $cles = collect(CarbonPeriod::create($debut, '1 month', now()->startOfMonth()))
            ->map(fn (Carbon $d) => $d->format('Y-m'))
            ->all();

        $evenements = $this->evenementsValorisation()->filter(fn ($e) => $e['date'] && $e['date']->gte($debut));
        $parMois = fn (Collection $events, callable $valeur) => array_map(
            fn (string $cle) => round($events->filter(fn ($e) => $e['date']->format('Y-m') === $cle)->sum($valeur), 1),
            $cles
        );

        $series = [
            'vetements' => $this->fill($cles, $this->monthlyCount('vetements', $debut)),
            'inscriptions' => $this->fill($cles, $this->monthlyCount('users', $debut)),
            'rendez_vous' => $this->fill($cles, $this->monthlyCount('rendez_vous', $debut)),
            'reparations' => $parMois($evenements->where('kind', 'reparation'), fn () => 1),
            'dons' => $parMois($evenements->where('kind', 'don'), fn () => 1),
            'sauves' => $parMois($evenements, fn () => 1),
            'co2' => $parMois($evenements, fn ($e) => $this->impact->pourVetement($e['type'], $e['kind'])['co2']),
            'eau' => $parMois($evenements, fn ($e) => $this->impact->pourVetement($e['type'], $e['kind'])['eau']),
        ];

        return [
            'mois' => $cles,
            'labels' => array_map(fn (string $cle) => Carbon::createFromFormat('Y-m-d', $cle.'-01')->locale('fr')->isoFormat('MMM YY'), $cles),
            'series' => $this->appliquerHistorique($cles, $series),
        ];
    }

    /**
     * Garde les $n derniers mois d'un résultat de seriesMensuelles().
     */
    public function derniersMois(array $resultat, int $n): array
    {
        return [
            'mois' => array_slice($resultat['mois'], -$n),
            'labels' => array_slice($resultat['labels'], -$n),
            'series' => array_map(fn (array $serie) => array_slice($serie, -$n), $resultat['series']),
        ];
    }

    /**
     * Premier mois d'activité réelle de la plateforme (null si aucune donnée).
     */
    public function premiereActivite(): ?Carbon
    {
        $dates = [];

        foreach (['users', 'vetements', 'rendez_vous', 'dons'] as $collection) {
            $doc = MongoCollections::get($collection)->findOne(
                ['created_at' => ['$type' => 'date']],
                ['sort' => ['created_at' => 1], 'projection' => ['created_at' => 1]] + MongoCollections::arrayTypeMap()
            );

            if ($date = MongoCollections::toDate($doc['created_at'] ?? null)) {
                $dates[] = $date;
            }
        }

        return $dates ? min($dates)->copy()->startOfMonth() : null;
    }

    /**
     * Les mois passés déjà consolidés (collections statistiques / impact_ecologique)
     * remplacent le calcul en temps réel ; le mois courant reste calculé en direct.
     */
    private function appliquerHistorique(array $cles, array $series): array
    {
        $historique = Statistique::with('impact')
            ->where('periode', '>=', Carbon::createFromFormat('Y-m-d', $cles[0].'-01')->startOfDay())
            ->where('periode', '<', now()->startOfMonth())
            ->get()
            ->keyBy(fn (Statistique $s) => $s->periode->format('Y-m'));

        $champs = [
            'vetements' => fn ($s) => $s->nb_vetements,
            'inscriptions' => fn ($s) => $s->nb_utilisateurs,
            'reparations' => fn ($s) => $s->nb_reparations,
            'dons' => fn ($s) => $s->nb_dons,
            'sauves' => fn ($s) => $s->impact?->vetements_sauves,
            'co2' => fn ($s) => $s->impact?->co2_evite_kg,
            'eau' => fn ($s) => $s->impact?->eau_economisee_litres,
        ];

        foreach ($cles as $i => $cle) {
            if (! $stat = $historique[$cle] ?? null) {
                continue;
            }

            foreach ($champs as $serie => $valeur) {
                $series[$serie][$i] = $valeur($stat) ?? $series[$serie][$i];
            }
        }

        return $series;
    }

    /**
     * Agrégats d'une période (mois) pour la consolidation.
     */
    public function periode(Carbon $debut): array
    {
        $fin = $debut->copy()->endOfMonth();
        $plage = ['$gte' => new UTCDateTime($debut), '$lte' => new UTCDateTime($fin)];
        $evenements = $this->evenementsValorisation()->filter(fn ($e) => $e['date'] && $e['date']->between($debut, $fin));
        $impact = $this->impact->total($evenements);

        return [
            'statistique' => [
                'nb_vetements' => $this->count('vetements', ['created_at' => $plage]),
                'nb_reparations' => $evenements->where('kind', 'reparation')->count(),
                'nb_dons' => $evenements->where('kind', 'don')->count(),
                'nb_utilisateurs' => $this->count('users', ['created_at' => $plage]),
                'nb_ateliers' => $this->count('ateliers', ['statut' => 'actif', 'created_at' => ['$lte' => new UTCDateTime($fin)]]),
            ],
            'impact' => [
                'vetements_sauves' => $impact['vetements'],
                'co2_evite_kg' => round($impact['co2'], 2),
                'eau_economisee_litres' => round($impact['eau'], 2),
            ],
        ];
    }

    /**
     * @return array<string, int> nombre de vêtements par statut
     */
    public function repartitionVetements(): array
    {
        return $this->groupCount('vetements', 'status');
    }

    /**
     * @return array<string, int> top types de vêtements déclarés
     */
    public function topTypes(int $limit = 6): array
    {
        return array_slice($this->groupCount('vetements', 'type'), 0, $limit, true);
    }

    /**
     * Rendez-vous par atelier : 30 derniers jours vs moyenne mensuelle des 3 mois précédents.
     *
     * @return list<array{atelier_id: string, nom: string, recent: int, moyenne: float}>
     */
    public function activiteAteliers(): array
    {
        $now = now();
        $rows = MongoCollections::get('rendez_vous')->aggregate([
            ['$match' => ['created_at' => ['$gte' => new UTCDateTime($now->copy()->subDays(120))]]],
            ['$group' => [
                '_id' => '$atelier_id',
                'recent' => ['$sum' => ['$cond' => [['$gte' => ['$created_at', new UTCDateTime($now->copy()->subDays(30))]], 1, 0]]],
                'precedent' => ['$sum' => ['$cond' => [['$lt' => ['$created_at', new UTCDateTime($now->copy()->subDays(30))]], 1, 0]]],
            ]],
        ], MongoCollections::arrayTypeMap())->toArray();

        $noms = $this->noms('ateliers', array_column($rows, '_id'), 'nom');

        return array_map(fn (array $row) => [
            'atelier_id' => (string) $row['_id'],
            'nom' => $noms[(string) $row['_id']] ?? 'Atelier inconnu',
            'recent' => (int) $row['recent'],
            'moyenne' => round($row['precedent'] / 3, 2),
        ], $rows);
    }

    /**
     * Vêtements « sauvés » (réparés, donnés ou recyclés), dédoublonnés par vêtement.
     *
     * Sources : statut du vêtement (module 1), RDV terminés (module 3), dons acceptés (module 4).
     *
     * @return Collection<int, array{vetement_id: string, kind: string, date: ?Carbon, type: ?string}>
     */
    public function evenementsValorisation(): Collection
    {
        if ($this->evenements !== null) {
            return $this->evenements;
        }

        $events = collect();
        $options = MongoCollections::arrayTypeMap();

        $vetements = MongoCollections::get('vetements')->find(
            ['status' => ['$in' => array_keys(self::VALORISATIONS_VETEMENT)]],
            ['projection' => ['status' => 1, 'type' => 1, 'updated_at' => 1]] + $options
        );
        foreach ($vetements as $v) {
            $events->push([
                'vetement_id' => (string) $v['_id'],
                'kind' => self::VALORISATIONS_VETEMENT[$v['status']],
                'date' => MongoCollections::toDate($v['updated_at'] ?? null),
                'type' => $v['type'] ?? null,
            ]);
        }

        $rdvs = MongoCollections::get('rendez_vous')->find(
            ['statut' => 'termine'],
            ['projection' => ['vetement_id' => 1, 'updated_at' => 1]] + $options
        );
        foreach ($rdvs as $rdv) {
            $events->push([
                'vetement_id' => (string) ($rdv['vetement_id'] ?? $rdv['_id']),
                'kind' => 'reparation',
                'date' => MongoCollections::toDate($rdv['updated_at'] ?? null),
                'type' => null,
            ]);
        }

        $dons = MongoCollections::get('dons')->find(
            ['statut' => 'accepte'],
            ['projection' => ['vetement_id' => 1, 'date_reponse' => 1, 'updated_at' => 1]] + $options
        );
        foreach ($dons as $don) {
            $events->push([
                'vetement_id' => (string) ($don['vetement_id'] ?? $don['_id']),
                'kind' => 'don',
                'date' => MongoCollections::toDate($don['date_reponse'] ?? $don['updated_at'] ?? null),
                'type' => null,
            ]);
        }

        // Un vêtement n'est compté qu'une fois : on garde sa première valorisation.
        $events = $events
            ->sortBy(fn ($e) => $e['date']?->timestamp ?? PHP_INT_MAX)
            ->unique('vetement_id')
            ->values();

        $types = $this->noms('vetements', $events->whereNull('type')->pluck('vetement_id')->all(), 'type');

        return $this->evenements = $events->map(function (array $e) use ($types) {
            $e['type'] ??= $types[$e['vetement_id']] ?? null;

            return $e;
        });
    }

    private function count(string $collection, array $filter = []): int
    {
        return MongoCollections::get($collection)->countDocuments($filter);
    }

    /**
     * @return array<string, int> "YYYY-MM" => nombre de documents créés
     */
    private function monthlyCount(string $collection, Carbon $depuis, array $match = []): array
    {
        $rows = MongoCollections::get($collection)->aggregate([
            ['$match' => $match + ['created_at' => ['$gte' => new UTCDateTime($depuis), '$type' => 'date']]],
            ['$group' => [
                '_id' => ['$dateToString' => ['format' => '%Y-%m', 'date' => '$created_at']],
                'n' => ['$sum' => 1],
            ]],
        ]);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['_id']] = $row['n'];
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    private function groupCount(string $collection, string $field): array
    {
        $rows = MongoCollections::get($collection)->aggregate([
            ['$group' => ['_id' => '$'.$field, 'n' => ['$sum' => 1]]],
            ['$sort' => ['n' => -1]],
        ]);

        $result = [];
        foreach ($rows as $row) {
            $result[(string) ($row['_id'] ?? 'non renseigné')] = $row['n'];
        }

        return $result;
    }

    private function fill(array $cles, array $valeurs): array
    {
        return array_map(fn (string $cle) => $valeurs[$cle] ?? 0, $cles);
    }

    /**
     * @return array<string, string> id => valeur du champ
     */
    private function noms(string $collection, array $ids, string $field): array
    {
        $oids = array_values(array_filter(array_map(fn ($id) => MongoCollections::objectId($id), array_unique(array_map('strval', $ids)))));

        if ($oids === []) {
            return [];
        }

        $docs = MongoCollections::get($collection)->find(
            ['_id' => ['$in' => $oids]],
            ['projection' => [$field => 1]] + MongoCollections::arrayTypeMap()
        );

        $result = [];
        foreach ($docs as $doc) {
            $result[(string) $doc['_id']] = $doc[$field] ?? null;
        }

        return $result;
    }
}
