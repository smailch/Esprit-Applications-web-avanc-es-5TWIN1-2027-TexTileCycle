<?php

namespace App\Modules\Dons\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use App\Modules\Dons\Models\Don;
use App\Modules\Vetements\Services\VetementService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DonController extends Controller
{
    use RendersBackOffice;

    public function __construct(
        private VetementService $vetements
    ) {
    }

    /** Liste des dons (admin voit tout, association voit les siens). */
    public function index(Request $request)
    {
        $user   = auth()->user();
        $statut = $request->get('statut');
        $query  = Don::with(['vetement', 'association', 'donateur'])
            ->orderBy('created_at', 'desc');

        // L'association ne voit que les dons qui lui sont destinés
        if ($user->role === \App\Modules\Auth\Models\User::ROLE_ASSOCIATION) {
            // Trouver l'association liée à ce user
            $assoc = \App\Modules\Associations\Models\Association::where('user_id', $user->_id)->first();
            if ($assoc) {
                $query->where('association_id', $assoc->_id);
            }
        }

        if ($statut && in_array($statut, Don::STATUTS, true)) {
            $query->where('statut', $statut);
        }

        $dons = $query->get();

        return $this->backView('back.dons.index', [
            'pageTitle' => 'Gestion des dons',
            'dons'      => $dons,
            'statuts'   => Don::STATUTS,
            'statut'    => $statut,
        ]);
    }

    /** Accepter un don. */
    public function accept(string $id)
    {
        $don = Don::findOrFail($id);

        if (! $don->isPending()) {
            return back()->withErrors(['don' => 'Ce don a déjà été traité.']);
        }

        $don->update([
            'statut'       => Don::STATUT_ACCEPTE,
            'date_reponse' => Carbon::now(),
        ]);

        $don->load(['vetement', 'association']);

        if ($don->vetement) {
            $this->vetements->cloturerParDon($don->vetement, $don->association?->nom);
        }

        return back()->with('success', 'Don accepté avec succès.');
    }

    /** Refuser un don. */
    public function refuse(string $id)
    {
        $don = Don::findOrFail($id);

        if (! $don->isPending()) {
            return back()->withErrors(['don' => 'Ce don a déjà été traité.']);
        }

        $don->update([
            'statut'       => Don::STATUT_REFUSE,
            'date_reponse' => Carbon::now(),
        ]);

        $don->load(['vetement', 'association']);

        if ($don->vetement) {
            $this->vetements->enregistrerRefusDon($don->vetement, $don->association?->nom);
        }

        return back()->with('success', 'Don refusé.');
    }

    /** Supprimer un don. */
    public function destroy(string $id)
    {
        Don::findOrFail($id)->delete();
        return back()->with('success', 'Don supprimé.');
    }
}
