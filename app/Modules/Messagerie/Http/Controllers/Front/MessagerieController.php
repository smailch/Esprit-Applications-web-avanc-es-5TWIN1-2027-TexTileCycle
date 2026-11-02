<?php

namespace App\Modules\Messagerie\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use App\Modules\Messagerie\Http\Controllers\Concerns\GereFilDeDiscussion;
use App\Modules\Messagerie\Http\Requests\StoreConversationRequest;
use App\Modules\Messagerie\Models\Conversation;
use App\Modules\Messagerie\Services\MessagerieService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Messagerie côté utilisateur : un citoyen écrit à l'équipe TexTileCycle, à un atelier
 * ou à une association ; le responsable d'un partenaire répond depuis son espace.
 * Citoyen : gabarit « trame » ; atelier / association : gabarit du back-office.
 */
class MessagerieController extends Controller
{
    use RendersBackOffice;
    use GereFilDeDiscussion;

    public function __construct(
        protected MessagerieService $messagerie
    ) {
    }

    public function index(Request $request)
    {
        if ($request->user()->isAdmin()) {
            return redirect()->route('back.messagerie.index');
        }

        return $this->vue($request, 'messagerie.index', $this->boite($request));
    }

    public function create(Request $request)
    {
        if ($request->user()->isAdmin()) {
            return redirect()->route('back.messagerie.create');
        }

        // Destinataire prérempli (bouton « Contacter » d'une fiche atelier) ou conservé après une erreur.
        $valeur = (string) old('destinataire', $request->query('destinataire', ''));
        $choisi = $valeur !== '' ? $this->messagerie->resoudreDestinataire($valeur, $request->user()) : null;

        return $this->vue($request, 'messagerie.create', [
            'sujet' => (string) $request->query('sujet', ''),
            'destinataire' => $choisi ? ['valeur' => $valeur, 'nom' => $choisi['nom']] : null,
        ]);
    }

    /** GET /messages/destinataires?q=… : suggestions (3 caractères minimum). */
    public function destinataires(Request $request): JsonResponse
    {
        return response()->json([
            'resultats' => $this->messagerie->rechercherDestinataires($request->user(), (string) $request->query('q', '')),
        ]);
    }

    public function store(StoreConversationRequest $request)
    {
        $destinataire = $request->destinataire();
        $data = $request->safe()->only(['sujet', 'message']);

        if ($destinataire['contexte']) {
            $data['contexte_type'] = class_basename($destinataire['contexte']);
            $data['contexte_id'] = (string) $destinataire['contexte']->getKey();
        }

        // Modération IA du premier message : un message bloqué n'ouvre pas de conversation.
        $moderation = $this->moderer($request->user(), $data['message']);
        if ($refus = $this->refusModeration($request, $moderation, 'message', fn () => null)) {
            return $refus;
        }

        $conversation = $this->messagerie->creer($request->user(), $request->user(), $data, $destinataire['partenaire'], $moderation);

        return redirect()
            ->route('front.messagerie.show', $conversation->getKey())
            ->with('success', 'Votre message a été envoyé à '.$destinataire['nom'].'.');
    }

    public function show(Request $request, string $conversation)
    {
        if ($request->user()->isAdmin()) {
            return redirect()->route('back.messagerie.show', $conversation);
        }

        $model = $this->trouver($conversation);

        return $this->vue($request, 'messagerie.show', $this->filVue($model, $request) + $this->boite($request));
    }

    protected function routeFil(Conversation $conversation): string
    {
        return route('front.messagerie.show', $conversation->getKey());
    }

    private function boite(Request $request): array
    {
        $filtres = $request->only(['filtre', 'q']);

        return [
            'conversations' => $this->messagerie->boite($request->user(), $filtres),
            'filtres' => $filtres,
        ];
    }

    /**
     * Même page pour tous, dans le gabarit adapté au rôle.
     */
    private function vue(Request $request, string $vue, array $data)
    {
        $data['pageTitle'] = 'Messages';

        if ($request->user()->canAccessBackOffice()) {
            return $this->backView($vue, $data + ['layout' => 'layouts.back']);
        }

        return view($vue, $data + ['layout' => 'layouts.trame']);
    }
}
