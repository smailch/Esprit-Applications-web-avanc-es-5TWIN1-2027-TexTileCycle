<?php

namespace App\Modules\Signalements\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use App\Modules\Signalements\Http\Requests\StoreSignalementRequest;
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
            'types' => Signalement::TYPES,
            'statuts' => Signalement::STATUTS,
            'cibles' => array_keys(Signalement::CIBLES),
        ]);
    }

    public function store(StoreSignalementRequest $request)
    {
        $this->signalements->create($request->validated(), $request->user());

        return back()->with('success', 'Signalement enregistré.');
    }

    public function update(Request $request, string $signalement)
    {
        $model = Signalement::findOrFail($signalement);

        $data = $request->validate([
            'type' => ['required', Rule::in(Signalement::TYPES)],
            'motif' => ['required', 'string', 'min:10', 'max:2000'],
            'note_admin' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->signalements->update($model, $data);

        return back()->with('success', 'Signalement mis à jour.');
    }

    public function moderer(Request $request, string $signalement)
    {
        $model = Signalement::findOrFail($signalement);

        $data = $request->validate([
            'statut' => ['required', Rule::in([Signalement::STATUT_TRAITE, Signalement::STATUT_REJETE])],
            'note_admin' => ['nullable', 'string', 'max:2000'],
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

        return back()->with('success', 'Signalement supprimé.');
    }
}
