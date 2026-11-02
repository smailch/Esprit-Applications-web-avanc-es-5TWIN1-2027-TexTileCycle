<?php

namespace App\Modules\Messagerie\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Models\User;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use App\Modules\Messagerie\Http\Controllers\Concerns\GereFilDeDiscussion;
use App\Modules\Messagerie\Http\Requests\Back\StoreConversationRequest;
use App\Modules\Messagerie\Http\Requests\Back\UpdateConversationRequest;
use App\Modules\Messagerie\Models\Conversation;
use App\Modules\Messagerie\Services\MessagerieService;
use Illuminate\Http\Request;

/**
 * Messagerie de l'administration : CRUD des conversations avec les citoyens et les partenaires.
 */
class ConversationController extends Controller
{
    use RendersBackOffice;
    use GereFilDeDiscussion;

    public function __construct(
        protected MessagerieService $messagerie
    ) {
    }

    public function index(Request $request)
    {
        return $this->backView('back.messagerie.index', $this->boite($request) + [
            'pageTitle' => 'Messagerie',
        ]);
    }

    public function create(Request $request)
    {
        $contexteType = array_key_exists((string) $request->query('contexte_type'), Conversation::CONTEXTES)
            ? $request->query('contexte_type')
            : null;
        $contexte = $contexteType ? Conversation::CONTEXTES[$contexteType]::find($request->query('contexte_id')) : null;
        $apercu = $contexte ? (new Conversation(['contexte_type' => $contexteType]))->setRelation('contexte', $contexte) : null;

        return $this->backView('back.messagerie.create', [
            'pageTitle' => 'Nouvelle conversation',
            'destinataires' => $this->messagerie->destinataires(),
            'prefill' => [
                'user_id' => (string) $request->query('user', ''),
                'sujet' => (string) $request->query('sujet', ''),
                'contexte_type' => $contexte ? $contexteType : null,
                'contexte_id' => $contexte ? (string) $contexte->getKey() : null,
            ],
            'contexteLabel' => $apercu?->contexteLabel(),
        ]);
    }

    public function store(StoreConversationRequest $request)
    {
        $destinataire = $request->destinataire();
        $conversation = $this->messagerie->creer($request->user(), $destinataire, $request->validated());

        return redirect()
            ->route('back.messagerie.show', $conversation->getKey())
            ->with('success', 'Conversation ouverte : '.$destinataire->name.' a reçu votre message.');
    }

    public function show(Request $request, string $conversation)
    {
        $model = $this->trouver($conversation);

        return $this->backView('back.messagerie.show', $this->filVue($model, $request) + $this->boite($request) + [
            'pageTitle' => 'Messagerie',
        ]);
    }

    public function edit(string $conversation)
    {
        $model = $this->trouver($conversation);
        $this->authorize('update', $model);

        return $this->backView('back.messagerie.edit', [
            'pageTitle' => 'Modifier la conversation',
            'conversation' => $model,
        ]);
    }

    public function update(UpdateConversationRequest $request, string $conversation)
    {
        $model = $this->trouver($conversation);
        $this->authorize('update', $model);
        $model->fill($request->validated())->save();

        return redirect()
            ->route('back.messagerie.show', $model->getKey())
            ->with('success', 'Conversation mise à jour.');
    }

    public function destroy(string $conversation)
    {
        $model = $this->trouver($conversation);
        $this->authorize('delete', $model);
        $this->messagerie->supprimer($model);

        return redirect()
            ->route('back.messagerie.index')
            ->with('success', 'Conversation et messages supprimés.');
    }

    protected function routeFil(Conversation $conversation): string
    {
        return route('back.messagerie.show', $conversation->getKey());
    }

    private function boite(Request $request): array
    {
        $filtres = $request->only(['filtre', 'role', 'q']);

        return [
            'conversations' => $this->messagerie->boite($request->user(), $filtres),
            'filtres' => $filtres,
            'roles' => [User::ROLE_CITOYEN => 'Citoyens', User::ROLE_ATELIER => 'Ateliers', User::ROLE_ASSOCIATION => 'Associations'],
        ];
    }
}
