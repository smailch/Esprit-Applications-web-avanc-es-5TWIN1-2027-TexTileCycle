<?php

namespace App\Modules\Ateliers\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Ateliers\Http\Controllers\Back\Concerns\GereEspaceAtelier;
use App\Modules\Ateliers\Http\Requests\StoreServiceRequest;
use App\Modules\Ateliers\Http\Requests\UpdateServiceRequest;
use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Ateliers\Services\ServiceCatalogueService;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MonServiceController extends Controller
{
    use GereEspaceAtelier;
    use RendersBackOffice;

    public const MODALE_CREATION = 'create';

    public const MODALE_EDITION = 'edit';

    public function __construct(
        private AtelierService $ateliers,
        private ServiceCatalogueService $catalogue,
    ) {
        $this->reserverAuRoleAtelier();
    }

    public function index(Request $request)
    {
        $atelier = $this->monAtelier($request);

        if (! $atelier) {
            return $this->vueAtelierNonConfigure();
        }

        $this->authorize('viewAny', Service::class);

        $services = $this->catalogue->list($atelier);

        return $this->backView('back.ateliers.espace.services', [
            'pageTitle' => 'Mon atelier',
            'atelier' => $atelier,
            'services' => $services,
            'nbServices' => $services->count(),
            'resume' => $this->resume($services),
            'modale' => $this->modaleARouvrir($request, $services),
        ]);
    }

    public function store(StoreServiceRequest $request)
    {
        $atelier = $this->monAtelier($request);

        if (! $atelier) {
            return $this->redirectionAtelierNonConfigure();
        }

        $this->authorize('create', [Service::class, $atelier]);

        $service = $this->catalogue->create($atelier, $request->validated());

        return redirect()
            ->route('back.ateliers.services')
            ->with('success', "Le service « {$service->nom} » a été ajouté à votre catalogue.");
    }

    public function update(UpdateServiceRequest $request, string $id)
    {
        $atelier = $this->monAtelier($request);

        if (! $atelier) {
            return $this->redirectionAtelierNonConfigure();
        }

        $this->authorize('update', $this->serviceDe($atelier, $id));

        $service = $this->catalogue->update($atelier, $id, $request->validated());

        return redirect()
            ->route('back.ateliers.services')
            ->with('success', "Le service « {$service->nom} » a été mis à jour.");
    }

    public function destroy(Request $request, string $id)
    {
        $atelier = $this->monAtelier($request);

        if (! $atelier) {
            return $this->redirectionAtelierNonConfigure();
        }

        $service = $this->serviceDe($atelier, $id);
        $this->authorize('delete', $service);

        $this->catalogue->delete($atelier, $id);

        return redirect()
            ->route('back.ateliers.services')
            ->with('success', "Le service « {$service->nom} » a été supprimé.");
    }

    /**
     * Recherche scopée sur l'atelier connecté : un service d'un autre atelier donne une 404.
     */
    private function serviceDe(Atelier $atelier, string $id): Service
    {
        return $this->catalogue->find($atelier, $id)->setRelation('atelier', $atelier);
    }

    /**
     * @param  Collection<int, Service>  $services
     * @return array{nombre: int, prixMinimal: ?string, dureeMoyenne: ?string}
     */
    private function resume(Collection $services): array
    {
        $prix = $services->pluck('prix_estime')->filter(fn ($p) => is_numeric($p));
        $durees = $services->pluck('duree_estimee')->filter(fn ($d) => is_numeric($d) && $d > 0);

        return [
            'nombre' => $services->count(),
            'prixMinimal' => $prix->isEmpty() ? null : Atelier::formatPrix((float) $prix->min()),
            'dureeMoyenne' => $durees->isEmpty() ? null : Service::formatDuree((int) round($durees->avg())),
        ];
    }

    /**
     * Après une erreur de validation, la modale concernée est rendue ouverte avec les anciennes valeurs.
     *
     * @param  Collection<int, Service>  $services
     * @return array{ouverte: bool, mode: string, service: ?Service}
     */
    private function modaleARouvrir(Request $request, Collection $services): array
    {
        $fermee = ['ouverte' => false, 'mode' => self::MODALE_CREATION, 'service' => null];

        if (! $request->session()->has('errors')) {
            return $fermee;
        }

        return match ($request->old('_modal')) {
            self::MODALE_CREATION => ['ouverte' => true, 'mode' => self::MODALE_CREATION, 'service' => null],
            self::MODALE_EDITION => ($service = $services->first(fn (Service $s) => (string) $s->getKey() === (string) $request->old('_service')))
                ? ['ouverte' => true, 'mode' => self::MODALE_EDITION, 'service' => $service]
                : $fermee,
            default => $fermee,
        };
    }
}
