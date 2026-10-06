<?php

namespace App\Modules\Ateliers\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Modules\Ateliers\Http\Requests\SearchAteliersRequest;
use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Vetements\Models\Vetement;
use App\Modules\Vetements\Services\VetementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AtelierController extends Controller
{
    private const PER_PAGE = 8;

    /**
     * Paramètres de la liste conservés vers la fiche et pour le lien retour.
     */
    private const LIST_QUERY_KEYS = ['q', 'service', 'ville', 'note_min', 'rayon_km', 'lat', 'lng', 'tri', 'page', 'vetement_id'];

    public const RAYONS_KM = [2, 5, 10, 20, 50];

    public const NOTES_MIN = ['3', '3.5', '4', '4.5'];

    public function __construct(
        private AtelierService $ateliers,
        private VetementService $vetements,
    ) {
    }

    public function index(SearchAteliersRequest $request)
    {
        $filters = $this->filters($request);
        $listQuery = $this->listQuery($request);
        $ateliers = $this->ateliers->search($filters, self::PER_PAGE);
        $vetementPourRdv = $this->vetementPourRdv($request);

        return view('front.ateliers', [
            'ateliers' => $ateliers,
            'total' => $ateliers->total(),
            'filters' => $filters,
            'hasPosition' => isset($filters['lat'], $filters['lng']),
            'activeFilters' => $this->activeFilters($filters, $listQuery),
            'serviceNames' => $this->ateliers->serviceNames(),
            'villes' => $this->ateliers->villes(),
            'markers' => $this->withUrls($this->ateliers->markers($filters), $listQuery),
            'listQuery' => $listQuery,
            'rayons' => self::RAYONS_KM,
            'notesMin' => self::NOTES_MIN,
            'jour' => Atelier::jourDe(Atelier::maintenant()),
            'vetementPourRdv' => $vetementPourRdv,
        ]);
    }

    public function show(Request $request, string $id)
    {
        $atelier = $this->ateliers->findActifOrFail($id);
        $marker = $this->ateliers->toMarkers(collect([$atelier]));

        $listQuery = $this->listQuery($request);

        return view('front.atelier-show', [
            'atelier' => $atelier,
            'markers' => $marker,
            'retourUrl' => route('front.ateliers', $listQuery),
            'jour' => Atelier::jourDe(Atelier::maintenant()),
            'ouvert' => $atelier->estOuvert(),
            'listQuery' => $listQuery,
            'vetementPourRdv' => $this->vetementPourRdv($request),
        ]);
    }

    public function map(SearchAteliersRequest $request): JsonResponse
    {
        $markers = $this->withUrls($this->ateliers->markers($this->filters($request)), $this->listQuery($request));

        return response()->json(['total' => count($markers), 'data' => $markers]);
    }

    private function filters(SearchAteliersRequest $request): array
    {
        return array_filter($request->validated(), fn ($v) => $v !== null && $v !== '');
    }

    /**
     * Uniquement des clés connues, en chaînes courtes : ces valeurs sont réinjectées dans des URL.
     */
    private function listQuery(Request $request): array
    {
        $query = array_filter(
            Arr::only($request->query(), self::LIST_QUERY_KEYS),
            fn ($v) => is_string($v) && $v !== '' && mb_strlen($v) <= 100
        );

        if (isset($query['vetement_id']) && ! preg_match('/^[0-9a-fA-F]{24}$/', $query['vetement_id'])) {
            unset($query['vetement_id']);
        }

        return $query;
    }

    /**
     * Vêtement à réparer choisi par le citoyen, conservé tout au long de la recherche d'atelier.
     */
    private function vetementPourRdv(Request $request): ?Vetement
    {
        $id = $this->listQuery($request)['vetement_id'] ?? null;
        $user = $request->user();

        if (! $id || ! $user) {
            return null;
        }

        return $this->vetements->findOwnedByUser($id, $user);
    }

    /**
     * @param  list<array<string, mixed>>  $markers
     * @return list<array<string, mixed>>
     */
    private function withUrls(array $markers, array $listQuery): array
    {
        return array_map(fn (array $m) => $m + [
            'url' => route('front.ateliers.show', ['id' => $m['id']] + $listQuery),
        ], $markers);
    }

    /**
     * Puces des filtres actifs, chacune avec l'URL qui la retire.
     *
     * @return list<array{label: string, url: string}>
     */
    private function activeFilters(array $filters, array $listQuery = []): array
    {
        $base = Arr::except($filters, ['page']);
        if (isset($listQuery['vetement_id'])) {
            $base['vetement_id'] = $listQuery['vetement_id'];
        }
        $sans = fn (array $cles) => route('front.ateliers', Arr::except($base, $cles));
        $puces = [];

        if (isset($filters['q'])) {
            $puces[] = ['label' => '« '.$filters['q'].' »', 'url' => $sans(['q'])];
        }

        if (isset($filters['service'])) {
            $puces[] = ['label' => 'Service : '.$filters['service'], 'url' => $sans(['service'])];
        }

        if (isset($filters['ville'])) {
            $puces[] = ['label' => 'Ville : '.$filters['ville'], 'url' => $sans(['ville'])];
        }

        if (isset($filters['note_min'])) {
            $puces[] = ['label' => 'Note ≥ '.str_replace('.', ',', (string) (float) $filters['note_min']), 'url' => $sans(['note_min'])];
        }

        if (isset($filters['lat'], $filters['lng'])) {
            $sansPosition = ['lat', 'lng', 'rayon_km'];

            if (($filters['tri'] ?? null) === AtelierService::TRI_DISTANCE) {
                $sansPosition[] = 'tri';
            }

            $puces[] = ['label' => 'Autour de moi', 'url' => $sans($sansPosition)];

            if (isset($filters['rayon_km'])) {
                $puces[] = ['label' => 'Rayon : '.(float) $filters['rayon_km'].' km', 'url' => $sans(['rayon_km'])];
            }
        }

        return $puces;
    }
}
