<?php

namespace App\Modules\Ateliers\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Ateliers\Http\Requests\StoreAtelierRequest;
use App\Modules\Ateliers\Http\Requests\UpdateAtelierRequest;
use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Ateliers\Support\HorairesFormulaire;
use App\Modules\Auth\Models\User;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AtelierController extends Controller
{
    use RendersBackOffice;

    private const PER_PAGE = 15;

    private const ROUTE_ESPACE_ATELIER = 'back.ateliers.profil';

    public function __construct(private AtelierService $ateliers)
    {
    }

    public function index(Request $request)
    {
        if ($request->user()?->role === User::ROLE_ATELIER) {
            return redirect()->route(self::ROUTE_ESPACE_ATELIER);
        }

        abort_unless($request->user()?->isAdmin(), 403, 'La gestion des ateliers est réservée aux administrateurs.');

        $this->authorize('viewAny', Atelier::class);

        $filters = array_filter([
            'q' => is_string($request->query('q')) ? mb_substr(trim($request->query('q')), 0, 100) : null,
            'statut' => in_array($request->query('statut'), Atelier::STATUTS, true) ? $request->query('statut') : null,
        ], fn ($v) => $v !== null && $v !== '');

        return $this->backView('back.ateliers.index', [
            'pageTitle' => 'Ateliers & services',
            'ateliers' => $this->ateliers->listForAdmin($filters, self::PER_PAGE),
            'stats' => $this->ateliers->statsAdmin(),
            'filters' => $filters,
            'statuts' => Atelier::STATUTS,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Atelier::class);

        return $this->backView('back.ateliers.create', [
            'pageTitle' => 'Nouvel atelier',
            'atelier' => new Atelier(['statut' => Atelier::STATUT_EN_ATTENTE]),
            'comptes' => $this->ateliers->comptesAtelierDisponibles(),
            'horaires' => HorairesFormulaire::pourFormulaire(old('horaires', HorairesFormulaire::PAR_DEFAUT)),
            'statuts' => Atelier::STATUTS,
        ]);
    }

    public function store(StoreAtelierRequest $request)
    {
        $this->authorize('create', Atelier::class);

        $atelier = $this->ateliers->create($request->validated());

        return redirect()
            ->route('back.ateliers')
            ->with('success', "L'atelier « {$atelier->nom} » a été créé avec le statut « {$atelier->statutLabel()} ».");
    }

    public function edit(string $id)
    {
        $atelier = $this->ateliers->findOrFail($id);
        $this->authorize('update', $atelier);

        return $this->backView('back.ateliers.edit', [
            'pageTitle' => 'Modifier un atelier',
            'atelier' => $atelier,
            'comptes' => collect(),
            'horaires' => HorairesFormulaire::pourFormulaire(old('horaires', $atelier->horairesSemaine())),
            'statuts' => Atelier::STATUTS,
        ]);
    }

    public function update(UpdateAtelierRequest $request, string $id)
    {
        $atelier = $this->ateliers->findOrFail($id);
        $this->authorize('update', $atelier);

        $atelier = $this->ateliers->update($id, $request->validated());

        return redirect()
            ->route('back.ateliers')
            ->with('success', "Les informations de « {$atelier->nom} » ont été enregistrées.");
    }

    public function destroy(string $id)
    {
        $atelier = $this->ateliers->findOrFail($id);
        $this->authorize('delete', $atelier);

        $nom = $atelier->nom;
        $nbServices = $atelier->services->count();
        $this->ateliers->delete($id);

        $detail = match ($nbServices) {
            0 => '',
            1 => ' ainsi que son service',
            default => " ainsi que ses {$nbServices} services",
        };

        return redirect()
            ->route('back.ateliers')
            ->with('success', "L'atelier « {$nom} »{$detail} a été supprimé.");
    }

    public function updateStatut(Request $request, string $id)
    {
        $data = $request->validate(
            ['statut' => ['required', Rule::in(Atelier::STATUTS)]],
            [
                'statut.required' => 'Choisissez un statut.',
                'statut.in' => 'Ce statut n\'existe pas.',
            ]
        );

        $atelier = $this->ateliers->findOrFail($id);
        $this->authorize('changerStatut', $atelier);

        $atelier = $this->ateliers->changerStatut($id, $data['statut']);

        $message = match ($atelier->statut) {
            Atelier::STATUT_ACTIF => "« {$atelier->nom} » est actif : il apparaît maintenant sur le site.",
            Atelier::STATUT_SUSPENDU => "« {$atelier->nom} » est suspendu : il n'apparaît plus sur le site.",
            default => "« {$atelier->nom} » est repassé en attente de validation.",
        };

        return redirect()->back(302, [], route('back.ateliers'))->with('success', $message);
    }
}
