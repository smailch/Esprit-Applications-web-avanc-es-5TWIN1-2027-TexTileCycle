<?php

namespace App\Modules\Signalements\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use App\Modules\Signalements\Http\Requests\Back\StoreSignalementRequest;
use App\Modules\Signalements\Http\Requests\Back\UpdateSignalementRequest;
use App\Modules\Signalements\Models\Signalement;
use App\Modules\Signalements\Services\SignalementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SignalementController extends Controller
{
    use RendersBackOffice;

    public function __construct(
        private SignalementService $signalements
    ) {
    }

    public function index(Request $request)
    {
        $filters = $request->only(['statut', 'type', 'cible_type']);

        return $this->backView('back.signalements.index', [
            'pageTitle' => 'Signalements',
            'filters' => $filters,
            'signalements' => $this->signalements->paginate($filters),
            'counts' => $this->signalements->countsByStatut(),
        ]);
    }

    public function create()
    {
        return $this->backView('back.signalements.create', [
            'pageTitle' => 'Nouveau signalement',
            'signalement' => new Signalement(),
        ] + $this->formOptions());
    }

    public function store(StoreSignalementRequest $request)
    {
        $signalement = $this->signalements->create(
            $request->validated(),
            User::findOrFail($request->validated('user_id'))
        );

        return redirect()
            ->route('back.signalements.show', $signalement->getKey())
            ->with('success', 'Signalement enregistré.');
    }

    public function show(string $signalement)
    {
        $model = $this->signalements->findOrFail($signalement);

        return $this->backView('back.signalements.show', [
            'pageTitle' => 'Signalement #'.substr((string) $model->getKey(), -6),
            'signalement' => $model,
            'autres' => $model->signalementsMemeCible(),
        ]);
    }

    public function edit(string $signalement)
    {
        $model = $this->signalements->findOrFail($signalement);

        return $this->backView('back.signalements.edit', [
            'pageTitle' => 'Modifier le signalement',
            'signalement' => $model,
        ] + $this->formOptions());
    }

    public function update(UpdateSignalementRequest $request, string $signalement)
    {
        $model = $this->signalements->findOrFail($signalement);
        $this->signalements->update($model, $request->validated(), $request->user());

        return redirect()
            ->route('back.signalements.show', $model->getKey())
            ->with('success', 'Signalement mis à jour.');
    }

    public function moderer(Request $request, string $signalement)
    {
        $model = $this->signalements->findOrFail($signalement);

        $data = $request->validate([
            'statut' => ['required', Rule::in([Signalement::STATUT_TRAITE, Signalement::STATUT_REJETE])],
            'note_admin' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => $request->input('statut') === Signalement::STATUT_REJETE)],
        ], [
            'note_admin.required' => 'Expliquez pourquoi le signalement est rejeté.',
        ]);

        $sanction = $this->signalements->moderer(
            $model,
            $data['statut'],
            $request->user(),
            $data['note_admin'] ?? null,
            $request->boolean('sanctionner')
        );

        $message = $data['statut'] === Signalement::STATUT_TRAITE ? 'Signalement traité.' : 'Signalement rejeté.';

        return back()->with('success', trim($message.' '.$sanction));
    }

    public function destroy(string $signalement)
    {
        $this->signalements->delete(Signalement::findOrFail($signalement));

        return redirect()
            ->route('back.signalements.index')
            ->with('success', 'Signalement supprimé.');
    }

    private function formOptions(): array
    {
        return [
            'cibles' => $this->signalements->ciblesDisponibles(),
            'auteurs' => $this->signalements->auteursDisponibles(),
        ];
    }
}
