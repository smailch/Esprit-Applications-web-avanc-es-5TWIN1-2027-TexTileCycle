<?php

namespace App\Modules\Messagerie\Http\Controllers\Concerns;

use App\Modules\Messagerie\Http\Requests\EnvoyerMessageRequest;
use App\Modules\Messagerie\Models\Conversation;
use App\Modules\Messagerie\Services\ModerationMessageService;
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
        $messages = $this->messagerie->messages($model, $apres, $request->user());

        if ($model->nonLusPour($request->user()) > 0) {
            $this->messagerie->marquerLue($model, $request->user());
        }

        return response()->json([
            'messages' => $messages->map(fn ($m) => $m->pourAffichage($request->user(), $model))->values(),
            'statut' => $model->statut,
            'ouverte' => $model->estOuverte(),
            'lu_par_autre_at' => $model->luParAutreCote($request->user())?->format('Y-m-d\TH:i:s.vP'),
        ]);
    }

    /** POST …/messages : envoi d'un message (JSON pour messagerie.js, redirection sinon). */
    public function envoyer(EnvoyerMessageRequest $request, string $conversation)
    {
        $model = $this->trouver($conversation);
        // Seuls les participants écrivent (un admin qui modère un échange citoyen ↔ partenaire lit seulement).
        abort_unless($model->estParticipant($request->user()), 403);

        if (! $model->estOuverte()) {
            $erreur = 'Cette conversation est fermée : vous ne pouvez plus y répondre.';

            return $request->expectsJson()
                ? response()->json(['message' => $erreur, 'errors' => ['contenu' => [$erreur]]], 422)
                : back()->withErrors(['contenu' => $erreur]);
        }

        $contenu = $request->validated('contenu');
        $moderation = $this->moderer($request->user(), $contenu);

        if ($refus = $this->refusModeration($request, $moderation, 'contenu', fn () => $this->messagerie->envoyer($model, $request->user(), $contenu, $moderation))) {
            return $refus;
        }

        $message = $this->messagerie->envoyer($model, $request->user(), $contenu, $moderation);

        return $request->expectsJson()
            ? response()->json(['message' => $message->load('auteur')->pourAffichage($request->user(), $model)], 201)
            : redirect($this->routeFil($model).'#fin');
    }

    /** GET …/non-lues : nombre de conversations non lues (badge du menu). */
    public function nonLues(Request $request): JsonResponse
    {
        return response()->json(['non_lues' => $this->messagerie->nonLues($request->user())]);
    }

    /**
     * Analyse IA du message avant envoi. Les messages de l'administration ne sont pas modérés.
     */
    protected function moderer(\App\Modules\Auth\Models\User $auteur, string $contenu): ?array
    {
        return $auteur->isAdmin() ? null : app(ModerationMessageService::class)->analyser($contenu);
    }

    /**
     * Réponse à renvoyer si la modération refuse le message, null sinon.
     *  - bloquer : le message est conservé comme preuve (via $conserver) puis refusé ;
     *  - avertir : refusé tant que l'auteur n'a pas coché / cliqué « Envoyer quand même » (champ confirmer).
     */
    protected function refusModeration(Request $request, ?array $moderation, string $champ, callable $conserver)
    {
        $verdict = $moderation['verdict'] ?? ModerationMessageService::OK;

        if ($verdict === ModerationMessageService::OK
            || ($verdict === ModerationMessageService::AVERTIR && $request->boolean('confirmer'))) {
            return null;
        }

        if ($verdict === ModerationMessageService::BLOQUER) {
            $conserver();
            $texte = 'Message non envoyé : '.$moderation['raison'].' Il a été transmis à l\'équipe de modération.';
        } else {
            $texte = $moderation['raison'].' Modifiez votre message ou confirmez l\'envoi.';
        }

        $details = [
            'verdict' => $verdict,
            'raison' => $moderation['raison'],
            'categories' => ModerationMessageService::libelleCategories($moderation['categories']),
        ];

        return $request->expectsJson()
            ? response()->json(['message' => $texte, 'errors' => [$champ => [$texte]], 'moderation' => $details], 422)
            : back()->withInput($verdict === ModerationMessageService::BLOQUER ? $request->except($champ) : $request->input())
                ->withErrors([$champ => $texte])
                ->with('moderation', $details);
    }

    protected function trouver(string $id): Conversation
    {
        return Conversation::with(['interlocuteur', 'admin', 'partenaire', 'contexte'])->findOrFail($id);
    }

    protected function filVue(Conversation $conversation, Request $request): array
    {
        $this->authorize('view', $conversation);
        $this->messagerie->marquerLue($conversation, $request->user());

        return [
            'conversation' => $conversation,
            'messages' => $this->messagerie->messages($conversation, null, $request->user()),
            'peutRepondre' => $request->user()->can('repondre', $conversation),
        ];
    }
}
