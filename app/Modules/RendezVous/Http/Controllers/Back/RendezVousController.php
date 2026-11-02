<?php

namespace App\Modules\RendezVous\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Auth\Models\User;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use App\Modules\RendezVous\Models\RendezVous;
use App\Modules\RendezVous\Services\RendezVousService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RendezVousController extends Controller
{
    use RendersBackOffice;

    public function __construct(
        private RendezVousService $rendezVous,
        private AtelierService $ateliers
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $statut = $request->query('statut');
        $atelier = $this->atelierConnecte($user);

        if ($atelier) {
            $items = $this->rendezVous->listerPourAtelier((string) $atelier->getKey(), $statut);
            $counts = $this->rendezVous->countsPourAtelier((string) $atelier->getKey());
        } else {
            abort_unless($user?->isAdmin(), 403);
            $items = $this->rendezVous->listerPourAdmin($statut);
            $counts = $this->countsAdmin($items);
        }

        return $this->backView('back.rendez-vous.index', [
            'pageTitle' => $atelier ? 'Mes rendez-vous' : 'Rendez-vous',
            'atelier' => $atelier,
            'rendezVous' => $items,
            'statut' => $statut,
            'counts' => $counts,
            'statuts' => RendezVous::STATUTS,
        ]);
    }

    public function updateStatut(Request $request, string $id)
    {
        $data = $request->validate([
            'statut' => ['required', Rule::in(RendezVous::STATUTS)],
        ]);

        $user = $request->user();
        $atelier = $this->atelierConnecte($user);

        if ($atelier) {
            $rdv = $this->rendezVous->changerStatutPourAtelier($id, (string) $atelier->getKey(), $data['statut']);
        } else {
            abort_unless($user?->isAdmin(), 403);
            $rdv = $this->rendezVous->changerStatutPourAdmin($id, $data['statut']);
        }

        $message = match ($rdv->statut) {
            RendezVous::STATUT_CONFIRME => 'Rendez-vous confirmé : la pièce passe en réparation.',
            RendezVous::STATUT_TERMINE => 'Rendez-vous terminé : la pièce est marquée réparée.',
            RendezVous::STATUT_ANNULE => 'Rendez-vous annulé.',
            default => 'Statut du rendez-vous mis à jour.',
        };

        return back()->with('success', $message);
    }

    private function atelierConnecte(?User $user): ?\App\Modules\Ateliers\Models\Atelier
    {
        if (! $user || $user->role !== User::ROLE_ATELIER) {
            return null;
        }

        return $this->ateliers->findOwnedByUser((string) $user->getKey());
    }

    /**
     * @param  \Illuminate\Support\Collection<int, RendezVous>  $items
     * @return array<string, int>
     */
    private function countsAdmin($items): array
    {
        $counts = array_fill_keys(RendezVous::STATUTS, 0);
        foreach ($items as $rdv) {
            $counts[$rdv->statut] = ($counts[$rdv->statut] ?? 0) + 1;
        }
        $counts['total'] = $items->count();

        return $counts;
    }
}
