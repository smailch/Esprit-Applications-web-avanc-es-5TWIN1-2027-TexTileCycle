<?php

namespace App\Modules\Ateliers\Services;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\UserService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use MongoDB\BSON\Regex;

class AtelierService
{
    public const TRI_PERTINENCE = 'pertinence';

    public const TRI_DISTANCE = 'distance';

    public const TRI_NOM = 'nom';

    public const TRIS = [self::TRI_PERTINENCE, self::TRI_DISTANCE, self::TRI_NOM];

    public const RAYON_TERRE_KM = 6371.0;

    public const COMPTE_INTROUVABLE = 'introuvable';

    public const COMPTE_DEJA_RATTACHE = 'deja_rattache';

    /**
     * Champs modifiables par l'admin comme par l'atelier lui-même.
     * user_id et statut sont réservés à l'admin ; note_moyenne et nb_avis ne viennent jamais d'un formulaire.
     */
    private const CHAMPS_EDITABLES = [
        'nom', 'specialite', 'description', 'adresse', 'ville',
        'telephone', 'latitude', 'longitude', 'horaires',
    ];

    /**
     * Filtres appliqués en base : search() et markers() avec les mêmes valeurs partagent une seule lecture.
     */
    private const FILTRES_REQUETE = ['q', 'service', 'ville', 'note_min'];

    /** @var array<string, Collection<int, Atelier>> */
    private array $actifsCache = [];

    // ---------------------------------------------------------------------
    // Front : recherche publique
    // ---------------------------------------------------------------------

    /**
     * Le filtrage se fait en Mongo ; distance, rayon, tri et pagination en PHP
     * (jeu de données petit : tous les ateliers actifs filtrés sont chargés).
     */
    public function search(array $filtres, int $perPage = 12, ?int $page = null): LengthAwarePaginatorContract
    {
        $ateliers = $this->refine($this->actifs($filtres), $filtres);
        $page = $page ?? Paginator::resolveCurrentPage();

        return (new LengthAwarePaginator(
            $ateliers->forPage($page, $perPage)->values(),
            $ateliers->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        ))->withQueryString();
    }

    /**
     * Requête Mongo des ateliers actifs correspondant aux filtres (sans tri ni distance).
     */
    public function activeSearchQuery(array $filtres): Builder
    {
        $query = Atelier::query()->actif();

        if (($q = $this->texte($filtres['q'] ?? null)) !== null) {
            $regex = new Regex(preg_quote($q), 'i');
            $idsParService = $this->atelierIdsDontUnServiceCorrespond($regex);

            $query->where(function (Builder $sub) use ($regex, $idsParService) {
                $sub->where('nom', 'regex', $regex)
                    ->orWhere('ville', 'regex', $regex)
                    ->orWhere('specialite', 'regex', $regex);

                if ($idsParService !== []) {
                    $sub->orWhereIn('_id', $idsParService);
                }
            });
        }

        if (($service = $this->texte($filtres['service'] ?? null)) !== null) {
            $query->whereIn('_id', $this->atelierIdsProposantService($service));
        }

        if (($ville = $this->texte($filtres['ville'] ?? null)) !== null) {
            $query->where('ville', $ville);
        }

        return $query->noteMin($filtres['note_min'] ?? null);
    }

    /**
     * Partie sans base de la recherche : distance_km, rayon, tri.
     *
     * @param  Collection<int, Atelier>  $ateliers
     * @return Collection<int, Atelier>
     */
    public function refine(Collection $ateliers, array $filtres): Collection
    {
        $position = $this->position($filtres);

        if ($position !== null) {
            [$lat, $lng] = $position;

            foreach ($ateliers as $atelier) {
                $atelier->setAttribute('distance_km', $this->hasCoordinates($atelier)
                    ? round(self::distanceKm($lat, $lng, (float) $atelier->latitude, (float) $atelier->longitude), 2)
                    : null);
            }

            $rayon = $filtres['rayon_km'] ?? null;

            if (is_numeric($rayon) && (float) $rayon > 0) {
                $ateliers = $ateliers->filter(fn (Atelier $a) => $a->distance_km !== null && $a->distance_km <= (float) $rayon);
            }
        }

        $tri = in_array($filtres['tri'] ?? null, self::TRIS, true) ? $filtres['tri'] : self::TRI_PERTINENCE;

        if ($tri === self::TRI_DISTANCE && $position === null) {
            $tri = self::TRI_PERTINENCE;
        }

        return $this->trier($ateliers, $tri)->values();
    }

    /**
     * Formule de Haversine, résultat en kilomètres.
     */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::RAYON_TERRE_KM * asin(min(1.0, sqrt($a)));
    }

    public static function formatDistance(?float $km): ?string
    {
        return $km === null ? null : number_format($km, 1, ',', '').' km';
    }

    /**
     * @return list<string>
     */
    public function serviceNames(): array
    {
        $idsActifs = Atelier::query()->actif()->pluck('_id')->map(fn ($id) => (string) $id)->all();

        if ($idsActifs === []) {
            return [];
        }

        return $this->distinctTriees(Service::query()->whereIn('atelier_id', $idsActifs)->pluck('nom'));
    }

    /**
     * @return list<string>
     */
    public function villes(): array
    {
        return $this->distinctTriees(Atelier::query()->actif()->pluck('ville'));
    }

    /**
     * Données légères pour la carte (ateliers sans coordonnées exclus).
     *
     * @return list<array<string, mixed>>
     */
    public function markers(array $filtres): array
    {
        return $this->toMarkers($this->refine($this->actifs($filtres), $filtres));
    }

    /**
     * Fiche publique : un atelier non actif est traité comme introuvable (404).
     */
    public function findActifOrFail(string $id): Atelier
    {
        return $this->ficheQuery($id)->firstOrFail();
    }

    public function ficheQuery(string $id): Builder
    {
        return Atelier::query()
            ->actif()
            ->with(['services' => fn ($q) => $q->orderBy('created_at')])
            ->where('_id', $id);
    }

    /**
     * @param  Collection<int, Atelier>  $ateliers
     * @return list<array<string, mixed>>
     */
    public function toMarkers(Collection $ateliers): array
    {
        return $ateliers
            ->filter(fn (Atelier $a) => $this->hasCoordinates($a))
            ->map(fn (Atelier $a) => [
                'id' => (string) $a->getKey(),
                'nom' => $a->nom,
                'ville' => $a->ville,
                'latitude' => (float) $a->latitude,
                'longitude' => (float) $a->longitude,
                'note' => $a->noteFormatee(),
                'specialite' => $a->specialiteAffichee(),
                'distance_km' => $a->distance_km,
                'distance' => self::formatDistance($a->distance_km),
            ])
            ->values()
            ->all();
    }

    // ---------------------------------------------------------------------
    // Admin
    // ---------------------------------------------------------------------

    public function listForAdmin(array $filtres, int $perPage = 15): LengthAwarePaginatorContract
    {
        $query = Atelier::query()->with(['user', 'services']);

        if (in_array($filtres['statut'] ?? null, Atelier::STATUTS, true)) {
            $query->where('statut', $filtres['statut']);
        }

        if (($q = $this->texte($filtres['q'] ?? null)) !== null) {
            $regex = new Regex(preg_quote($q), 'i');

            $query->where(function (Builder $sub) use ($regex) {
                $sub->where('nom', 'regex', $regex)
                    ->orWhere('ville', 'regex', $regex)
                    ->orWhere('specialite', 'regex', $regex)
                    ->orWhere('adresse', 'regex', $regex);
            });
        }

        return $query->orderByDesc('created_at')->paginate($perPage)->withQueryString();
    }

    public function findOrFail(string $id): Atelier
    {
        return Atelier::query()->with(['services', 'user'])->findOrFail($id);
    }

    /**
     * @return array{total: int, actif: int, en_attente: int, suspendu: int}
     */
    public function statsAdmin(): array
    {
        $parStatut = Atelier::query()->get(['statut'])->countBy('statut');
        $stats = ['total' => $parStatut->sum()];

        foreach (Atelier::STATUTS as $statut) {
            $stats[$statut] = (int) ($parStatut[$statut] ?? 0);
        }

        return $stats;
    }

    /**
     * Comptes de rôle atelier sans atelier rattaché (pour le select de création).
     *
     * @return Collection<int, User>
     */
    public function comptesAtelierDisponibles(): Collection
    {
        $pris = Atelier::query()->pluck('user_id')->filter()->map(fn ($id) => (string) $id)->values()->all();

        return User::query()
            ->where('role', User::ROLE_ATELIER)
            ->when($pris !== [], fn ($q) => $q->whereNotIn('_id', $pris))
            ->orderBy('name')
            ->get();
    }

    /**
     * null si le compte peut porter l'atelier, sinon COMPTE_INTROUVABLE ou COMPTE_DEJA_RATTACHE.
     */
    public function verifierCompteAtelier(string $userId, ?string $ignoreAtelierId = null): ?string
    {
        $user = User::query()->where('_id', $userId)->first();

        if (! $user || $user->role !== User::ROLE_ATELIER) {
            return self::COMPTE_INTROUVABLE;
        }

        $dejaRattache = Atelier::query()
            ->where('user_id', $userId)
            ->when($ignoreAtelierId, fn ($q) => $q->where('_id', '!=', $ignoreAtelierId))
            ->exists();

        return $dejaRattache ? self::COMPTE_DEJA_RATTACHE : null;
    }

    public function create(array $data): Atelier
    {
        $atelier = new Atelier($this->champsEditables($data));
        $atelier->user_id = (string) $data['user_id'];
        $atelier->statut = $data['statut'] ?? Atelier::STATUT_EN_ATTENTE;
        $atelier->horaires ??= self::normaliserHoraires(null);
        $atelier->save();

        return $atelier;
    }

    public function update(string $id, array $data): Atelier
    {
        $atelier = Atelier::query()->findOrFail($id);
        $atelier->fill($this->champsEditables($data));

        if (array_key_exists('user_id', $data)) {
            $atelier->user_id = (string) $data['user_id'];
        }

        if (array_key_exists('statut', $data)) {
            $atelier->statut = $this->statutValide($data['statut']);
        }

        $atelier->save();

        return $atelier;
    }

    public function delete(string $id): void
    {
        $atelier = Atelier::query()->findOrFail($id);

        Service::query()->where('atelier_id', (string) $atelier->getKey())->delete();
        $atelier->delete();
    }

    public function changerStatut(string $id, string $statut): Atelier
    {
        $statut = $this->statutValide($statut);

        $atelier = Atelier::query()->findOrFail($id);
        $atelier->statut = $statut;
        $atelier->save();

        app(UserService::class)->syncActiveFromPartnerStatut(
            $atelier->user_id !== null ? (string) $atelier->user_id : null,
            $statut
        );

        return $atelier;
    }

    // ---------------------------------------------------------------------
    // Atelier connecté
    // ---------------------------------------------------------------------

    public function createOwn(string $userId, array $data): Atelier
    {
        if (Atelier::query()->where('user_id', $userId)->exists()) {
            throw ValidationException::withMessages([
                'nom' => 'Votre fiche atelier existe déjà. Vous pouvez la modifier depuis votre profil.',
            ]);
        }

        unset($data['statut'], $data['user_id']);

        return $this->create(array_merge($data, [
            'user_id' => $userId,
            'statut' => Atelier::STATUT_EN_ATTENTE,
        ]));
    }

    public function findOwnedByUser(string $userId): ?Atelier
    {
        return Atelier::query()->with('services')->where('user_id', $userId)->first();
    }

    public function updateOwn(string $userId, array $data): Atelier
    {
        $atelier = Atelier::query()->where('user_id', $userId)->firstOrFail();
        $atelier->fill($this->champsEditables($data));
        $atelier->save();

        return $atelier;
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Toujours les 7 jours dans l'ordre ; un jour absent, null ou vide devient [] (fermé).
     *
     * @return array<string, list<array{0:string,1:string}>>
     */
    public static function normaliserHoraires(?array $horaires): array
    {
        $normalises = [];

        foreach (Atelier::JOURS as $jour) {
            $plages = $horaires[$jour] ?? [];
            $normalises[$jour] = is_array($plages)
                ? array_values(array_map(fn ($plage) => [(string) $plage[0], (string) $plage[1]], $plages))
                : [];
        }

        return $normalises;
    }

    /**
     * @return Collection<int, Atelier>
     */
    private function actifs(array $filtres): Collection
    {
        $cle = serialize(array_map(
            fn ($v) => is_string($v) ? trim($v) : $v,
            array_intersect_key($filtres, array_flip(self::FILTRES_REQUETE))
        ));

        return $this->actifsCache[$cle] ??= $this->fetchActifs($filtres);
    }

    /**
     * @return Collection<int, Atelier>
     */
    protected function fetchActifs(array $filtres): Collection
    {
        return $this->activeSearchQuery($filtres)->with('services')->get();
    }

    /**
     * @return list<string>
     */
    protected function atelierIdsDontUnServiceCorrespond(Regex $regex): array
    {
        return $this->idsUniques(Service::query()->where('nom', 'regex', $regex)->pluck('atelier_id'));
    }

    /**
     * @return list<string>
     */
    protected function atelierIdsProposantService(string $nom): array
    {
        return $this->idsUniques(Service::query()->where('nom', $nom)->pluck('atelier_id'));
    }

    private function champsEditables(array $data): array
    {
        $champs = array_intersect_key($data, array_flip(self::CHAMPS_EDITABLES));

        if (array_key_exists('horaires', $champs)) {
            $champs['horaires'] = self::normaliserHoraires($champs['horaires']);
        }

        return $champs;
    }

    private function statutValide(mixed $statut): string
    {
        if (! in_array($statut, Atelier::STATUTS, true)) {
            throw ValidationException::withMessages([
                'statut' => 'Le statut doit être : '.implode(', ', Atelier::STATUTS).'.',
            ]);
        }

        return $statut;
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function position(array $filtres): ?array
    {
        $lat = $filtres['lat'] ?? null;
        $lng = $filtres['lng'] ?? null;

        return is_numeric($lat) && is_numeric($lng) ? [(float) $lat, (float) $lng] : null;
    }

    private function hasCoordinates(Atelier $atelier): bool
    {
        return is_numeric($atelier->latitude) && is_numeric($atelier->longitude);
    }

    /**
     * @param  Collection<int, Atelier>  $ateliers
     * @return Collection<int, Atelier>
     */
    private function trier(Collection $ateliers, string $tri): Collection
    {
        $nom = fn (Atelier $a) => mb_strtolower(Str::ascii((string) $a->nom));

        return $ateliers->sort(match ($tri) {
            self::TRI_NOM => fn (Atelier $a, Atelier $b) => $nom($a) <=> $nom($b),
            self::TRI_DISTANCE => fn (Atelier $a, Atelier $b) => [$a->distance_km === null, $a->distance_km, -(float) $a->note_moyenne, $nom($a)]
                <=> [$b->distance_km === null, $b->distance_km, -(float) $b->note_moyenne, $nom($b)],
            default => fn (Atelier $a, Atelier $b) => [(float) $b->note_moyenne, (int) $b->nb_avis, $nom($a)]
                <=> [(float) $a->note_moyenne, (int) $a->nb_avis, $nom($b)],
        });
    }

    private function texte(mixed $valeur): ?string
    {
        if (! is_string($valeur)) {
            return null;
        }

        $valeur = trim($valeur);

        return $valeur === '' ? null : $valeur;
    }

    /**
     * @return list<string>
     */
    private function idsUniques(Collection $ids): array
    {
        return $ids->filter()->map(fn ($id) => (string) $id)->unique()->values()->all();
    }

    /**
     * @return list<string>
     */
    private function distinctTriees(Collection $valeurs): array
    {
        return $valeurs
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn (string $v) => trim($v))
            ->unique()
            ->sortBy(fn (string $v) => mb_strtolower(Str::ascii($v)))
            ->values()
            ->all();
    }
}
