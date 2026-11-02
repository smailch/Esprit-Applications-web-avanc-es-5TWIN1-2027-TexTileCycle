<?php

namespace App\Modules\Messagerie\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use App\Modules\Messagerie\Http\Controllers\Concerns\GereFilDeDiscussion;
use App\Modules\Messagerie\Http\Requests\StoreConversationRequest;
use App\Modules\Messagerie\Models\Conversation;
use App\Modules\Messagerie\Services\MessagerieService;
use Illuminate\Http\Request;

/**
 * Messagerie côté utilisateur : un citoyen (gabarit « trame ») ou le responsable
 * d'un atelier / d'une association (gabarit du back-office) échange avec l'administration.
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

        return $this->vue($request, 'messagerie.create', [
            'sujet' => (string) $request->query('sujet', ''),
        ]);
    }

    public function store(StoreConversationRequest $request)
    {
        $conversation = $this->messagerie->creer($request->user(), $request->user(), $request->validated());

        return redirect()
            ->route('front.messagerie.show', $conversation->getKey())
            ->with('success', "Votre message a été envoyé à l'équipe TexTileCycle.");
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
