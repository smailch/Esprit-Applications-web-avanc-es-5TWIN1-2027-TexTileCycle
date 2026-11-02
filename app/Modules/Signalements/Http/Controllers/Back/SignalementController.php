<?php

namespace App\Modules\Signalements\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use App\Modules\Signalements\Http\Requests\Back\StoreSignalementRequest;
use App\Modules\Signalements\Http\Requests\Back\UpdateSignalementRequest;
use App\Modules\Signalements\Models\Signalement;
use App\Modules\Signalements\Services\SignalementService;
use App\Modules\Signalements\Services\TriageSignalementService;
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
        $filters = $request->only(['statut', 'type', 'cible_type', 'tri']);

        return $this->backView('back.signalements.index', [
            'pageTitle' => 'Signalements',
            'filters' => $filters,
            'signalements' => $this->signalements->paginate($filters),
            'counts' => $this->signalements->countsByStatut(),
            'nonAnalyses' => Signalement::where('statut', Signalement::STATUT_EN_ATTENTE)->whereNull('triage')->count(),
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

    /** GET /admin/signalements/compteur : signalements en attente (badge rouge du menu). */
    public function compteur()
    {
        return response()->json(['non_lues' => Signalement::where('statut', Signalement::STATUT_EN_ATTENTE)->count()]);
    }

    /** Lance (ou relance) le triage IA d'un signalement. */
    public function analyser(string $signalement, TriageSignalementService $triage)
    {
        $resultat = $triage->trier(Signalement::findOrFail($signalement));

        return back()->with('success', 'Analyse IA terminée : gravité '.$resultat['gravite'].'/5, '
            .mb_strtolower(TriageSignalementService::DECISIONS[$resultat['decision']]).' suggéré'
            .($resultat['source'] === 'local' ? ' (analyse locale, IA indisponible).' : '.'));
    }

    /** Triage IA des signalements en attente pas encore analysés (par lots de 10). */
    public function analyserEnAttente(TriageSignalementService $triage)
    {
        $nb = $triage->trierEnAttente(10);

        return back()->with('success', $nb
            ? "{$nb} signalement(s) analysé(s) par l'IA."
            : 'Tous les signalements en attente sont déjà analysés.');
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
