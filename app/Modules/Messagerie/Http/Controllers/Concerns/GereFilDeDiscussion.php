<?php

namespace App\Modules\Messagerie\Http\Controllers\Concerns;

use App\Modules\Messagerie\Http\Requests\EnvoyerMessageRequest;
use App\Modules\Messagerie\Models\Conversation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Actions communes au fil de discussion (admin et utilisateurs) :
 * polling des nouveaux messages, envoi, compteur de non-lus.
 * Le contrôleur qui l'utilise fournit $this->messagerie et routeFil().
 */
trait GereFilDeDiscussion
{
    abstract protected function routeFil(Conversation $conversation): string;

    /** GET …/messages?apres=<date ISO> : nouveaux messages depuis le dernier reçu (JSON). */
    public function messages(Request $request, string $conversation): JsonResponse
    {
        $model = $this->trouver($conversation);
        $this->authorize('view', $model);

        $apres = rescue(fn () => $request->filled('apres') ? Carbon::parse($request->query('apres')) : null, null, false);
        $messages = $this->messagerie->messages($model, $apres);

        if ($model->nonLusPour($request->user()) > 0) {
            $this->messagerie->marquerLue($model, $request->user());
        }

        return response()->json([
            'messages' => $messages->map->pourAffichage($request->user())->values(),
            'statut' => $model->statut,
            'ouverte' => $model->estOuverte(),
            'lu_par_autre_at' => $model->luParAutreCote($request->user())?->format('Y-m-d\TH:i:s.vP'),
        ]);
    }

    /** POST …/messages : envoi d'un message (JSON pour messagerie.js, redirection sinon). */
    public function envoyer(EnvoyerMessageRequest $request, string $conversation)
    {
        $model = $this->trouver($conversation);
        $this->authorize('view', $model);

        if (! $model->estOuverte()) {
            $erreur = 'Cette conversation est fermée : vous ne pouvez plus y répondre.';

            return $request->expectsJson()
                ? response()->json(['message' => $erreur, 'errors' => ['contenu' => [$erreur]]], 422)
                : back()->withErrors(['contenu' => $erreur]);
        }

        $message = $this->messagerie->envoyer($model, $request->user(), $request->validated('contenu'));

        return $request->expectsJson()
            ? response()->json(['message' => $message->load('auteur')->pourAffichage($request->user())], 201)
            : redirect($this->routeFil($model).'#fin');
    }

    /** GET …/non-lues : nombre de conversations non lues (badge du menu). */
    public function nonLues(Request $request): JsonResponse
    {
        return response()->json(['non_lues' => $this->messagerie->nonLues($request->user())]);
    }

    protected function trouver(string $id): Conversation
    {
        return Conversation::with(['interlocuteur', 'admin', 'contexte'])->findOrFail($id);
    }

    protected function filVue(Conversation $conversation, Request $request): array
    {
        $this->authorize('view', $conversation);
        $this->messagerie->marquerLue($conversation, $request->user());

        return [
            'conversation' => $conversation,
            'messages' => $this->messagerie->messages($conversation),
            'peutRepondre' => $request->user()->can('repondre', $conversation),
        ];
    }
}
