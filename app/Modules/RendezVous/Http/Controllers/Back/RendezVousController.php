<?php

namespace App\Modules\RendezVous\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Auth\Models\User;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use App\Modules\RendezVous\Http\Requests\UpdateStatutRendezVousRequest;
use App\Modules\RendezVous\Models\RendezVous;
use App\Modules\RendezVous\Services\RendezVousService;
use Illuminate\Http\Request;

/**
 * Rôle atelier : uniquement les RDV de son atelier, retrouvé par AtelierService::findOwnedByUser
 * (jamais par un identifiant de la requête). Admin : tous les RDV, filtrables par atelier.
 */
class RendezVousController extends Controller
{
    use RendersBackOffice;

    private const PER_PAGE = 15;

    public function __construct(
        private AtelierService $ateliers,
        private RendezVousService $rendezVous,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $estAdmin = $user->isAdmin();
        $atelier = null;

        if (! $estAdmin) {
            abort_unless($user->role === User::ROLE_ATELIER, 403, 'Les rendez-vous sont réservés aux comptes atelier.');

            $atelier = $this->ateliers->findOwnedByUser((string) $user->getKey());

            if (! $atelier) {
                return $this->backView('back.ateliers.espace.vide', ['pageTitle' => 'Rendez-vous']);
            }
        }

        $this->authorize('viewAny', RendezVous::class);

        $filters = $this->filters($request, $estAdmin);
        $atelierId = $atelier ? (string) $atelier->getKey() : ($filters['atelier'] ?? null);

        return $this->backView('back.rendez-vous.index', [
            'pageTitle' => 'Rendez-vous',
            'rdvs' => $this->rendezVous->lister($atelierId, $filters, self::PER_PAGE),
            'stats' => $this->rendezVous->statistiques($atelierId),
            'filters' => $filters,
            'atelier' => $atelier,
            'estAdmin' => $estAdmin,
            'ateliersFiltre' => $estAdmin ? $this->rendezVous->ateliersPourFiltre() : collect(),
            'aujourdhui' => Atelier::maintenant()->format('Y-m-d'),
        ]);
    }

    public function updateStatut(UpdateStatutRendezVousRequest $request, string $id)
    {
        $user = $request->user();
        $atelier = null;

        if ($user->isAdmin()) {
            $rdv = $this->rendezVous->findOrFail($id);
        } else {
            abort_unless($user->role === User::ROLE_ATELIER, 403, 'Les rendez-vous sont réservés aux comptes atelier.');

            $atelier = $this->ateliers->findOwnedByUser((string) $user->getKey());
            abort_unless($atelier !== null, 404);

            $rdv = $this->rendezVous->findPourAtelier($atelier, $id);
        }

        $this->authorize('changerStatut', $rdv);

        $data = $request->validated();
        $rdv = $this->rendezVous->changerStatut($rdv, $data['statut'], $data['motif'] ?? null, $atelier);

        $quand = mb_strtolower($rdv->dateFormatee()).' à '.$rdv->heure;
        $message = match ($rdv->statut) {
            RendezVous::STATUT_CONFIRME => "Le rendez-vous du {$quand} est confirmé.",
            RendezVous::STATUT_REFUSE => "Le rendez-vous du {$quand} a été refusé.",
            RendezVous::STATUT_TERMINE => "Le rendez-vous du {$quand} est marqué comme terminé.",
            default => "Le rendez-vous du {$quand} a été annulé.",
        };

        return redirect()->back(302, [], route('back.rdv'))->with('success', $message);
    }

    /**
     * @return array{statut?: string, periode?: string, atelier?: string}
     */
    private function filters(Request $request, bool $estAdmin): array
    {
        $atelier = $request->query('atelier');

        return array_filter([
            'statut' => in_array($request->query('statut'), RendezVous::STATUTS, true) ? $request->query('statut') : null,
            'periode' => in_array($request->query('periode'), RendezVousService::PERIODES, true) ? $request->query('periode') : null,
            'atelier' => $estAdmin && is_string($atelier) && preg_match(RendezVousService::OBJECT_ID, $atelier) ? $atelier : null,
        ], fn ($v) => $v !== null);
    }
}
