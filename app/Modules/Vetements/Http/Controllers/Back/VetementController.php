<?php

namespace App\Modules\Vetements\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Auth\Models\User;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use App\Modules\Vetements\Models\Vetement;
use App\Modules\Vetements\Services\VetementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VetementController extends Controller
{
    use RendersBackOffice;

    public function __construct(
        private VetementService $vetements,
        private AtelierService $ateliers
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $atelier = $this->atelierConnecte($user);

        if ($atelier) {
            $items = $this->vetements->listForAtelier((string) $atelier->getKey());
            $counts = $this->vetements->countsForAtelier((string) $atelier->getKey());
            $pageTitle = 'Pièces à traiter';
        } else {
            abort_unless($user?->isAdmin(), 403);
            $items = $this->vetements->listAllForBackOffice();
            $counts = $items->countBy('status')->all();
            $pageTitle = 'Vêtements';
        }

        return $this->backView('back.vetements.index', [
            'pageTitle' => $pageTitle,
            'atelier' => $atelier,
            'vetements' => $items,
            'counts' => $counts,
            'statut' => $request->query('statut'),
        ]);
    }

    public function traiter(Request $request, string $id)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([Vetement::STATUS_EN_REPARATION, Vetement::STATUS_REPARE])],
        ]);

        $user = $request->user();
        $atelier = $this->atelierConnecte($user);
        abort_unless($atelier, 403, 'Seul l\'atelier destinataire peut traiter cette pièce.');

        $vetement = $this->vetements->traiterPourAtelier($id, (string) $atelier->getKey(), $data['status']);

        $message = $data['status'] === Vetement::STATUS_REPARE
            ? 'Pièce marquée réparée.'
            : 'Réparation démarrée pour cette pièce.';

        return back()->with('success', $message);
    }

    private function atelierConnecte(?User $user): ?\App\Modules\Ateliers\Models\Atelier
    {
        if (! $user || $user->role !== User::ROLE_ATELIER) {
            return null;
        }

        return $this->ateliers->findOwnedByUser((string) $user->getKey());
    }
}
