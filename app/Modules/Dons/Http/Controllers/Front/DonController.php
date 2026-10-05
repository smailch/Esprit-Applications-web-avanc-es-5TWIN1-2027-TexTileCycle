<?php

namespace App\Modules\Dons\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Modules\Associations\Models\Association;
use App\Modules\Dons\Models\Don;
use App\Modules\Vetements\Models\Vetement;
use Illuminate\Http\Request;

class DonController extends Controller
{
    /** Page de proposition de don : liste les associations + vêtements du citoyen. */
    public function index(Request $request)
    {
        $user         = auth()->user();
        $associations = Association::where('statut', Association::STATUT_ACTIF)
            ->orderBy('created_at', 'desc')
            ->get();

        // Vêtements du citoyen éligibles au don (pas encore donnés/recyclés)
        $vetements = Vetement::where('user_id', $user->_id)
            ->whereIn('status', [Vetement::STATUS_EN_ATTENTE, Vetement::STATUS_EN_REPARATION])
            ->orderBy('created_at', 'desc')
            ->get();

        // Dons déjà proposés par le citoyen
        $dons = Don::where('user_id', $user->_id)
            ->with(['vetement', 'association'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('front.dons', [
            'associations' => $associations,
            'vetements'    => $vetements,
            'dons'         => $dons,
            'donation'     => true,
        ]);
    }

    /** Soumettre une proposition de don. */
    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'vetement_id'    => 'required|string',
            'association_id' => 'required|string',
            'message'        => 'nullable|string|max:500',
        ]);

        // Vérifier que le vêtement appartient bien au citoyen
        $vetement = Vetement::where('_id', $data['vetement_id'])
            ->where('user_id', $user->_id)
            ->firstOrFail();

        // Vérifier que l'association existe et est active
        Association::where('_id', $data['association_id'])
            ->where('statut', Association::STATUT_ACTIF)
            ->firstOrFail();

        // Vérifier qu'un don identique n'est pas déjà en attente
        $existing = Don::where('vetement_id', $data['vetement_id'])
            ->where('statut', Don::STATUT_EN_ATTENTE)
            ->first();

        if ($existing) {
            return back()->withErrors(['vetement_id' => 'Ce vêtement a déjà une proposition de don en attente.']);
        }

        Don::create([
            'vetement_id'    => $data['vetement_id'],
            'association_id' => $data['association_id'],
            'user_id'        => $user->_id,
            'message'        => $data['message'] ?? null,
            'statut'         => Don::STATUT_EN_ATTENTE,
        ]);

        // Mettre le vêtement en statut "donné" si accepté (pour l'instant juste marqué)
        $vetement->update(['intended_action' => Vetement::ACTION_DON]);

        return back()->with('success', 'Votre proposition de don a été envoyée avec succès !');
    }

    /** Modifier un don en attente. */
    public function update(Request $request, string $id)
    {
        $user = auth()->user();
        $don = Don::where('_id', $id)->where('user_id', $user->_id)->firstOrFail();

        if (! $don->isPending()) {
            return back()->withErrors(['don' => 'Impossible de modifier un don déjà traité.']);
        }

        $data = $request->validate([
            'vetement_id'    => 'required|string',
            'association_id' => 'required|string',
            'message'        => 'nullable|string|max:500',
        ]);

        // Vérifier que le vêtement appartient bien au citoyen
        $vetement = Vetement::where('_id', $data['vetement_id'])
            ->where('user_id', $user->_id)
            ->firstOrFail();

        // Vérifier que l'association existe et est active
        Association::where('_id', $data['association_id'])
            ->where('statut', Association::STATUT_ACTIF)
            ->firstOrFail();

        // Si on change de vêtement, on vérifie que le nouveau n'est pas déjà dans un don en attente
        if ($data['vetement_id'] !== $don->vetement_id) {
            $existing = Don::where('vetement_id', $data['vetement_id'])
                ->where('statut', Don::STATUT_EN_ATTENTE)
                ->first();

            if ($existing) {
                return back()->withErrors(['vetement_id' => 'Ce vêtement a déjà une proposition de don en attente.']);
            }
            
            // L'ancien vêtement redevient normal (plus spécifiquement destiné au don)
            if ($oldVetement = Vetement::find($don->vetement_id)) {
                $oldVetement->update(['intended_action' => null]);
            }
            // Le nouveau vêtement est destiné au don
            $vetement->update(['intended_action' => Vetement::ACTION_DON]);
        }

        $don->update([
            'vetement_id'    => $data['vetement_id'],
            'association_id' => $data['association_id'],
            'message'        => $data['message'] ?? null,
        ]);

        return back()->with('success', 'Votre proposition de don a été modifiée avec succès !');
    }

    /** Annuler un don en attente. */
    public function destroy(string $id)
    {
        $user = auth()->user();
        $don  = Don::where('_id', $id)->where('user_id', $user->_id)->firstOrFail();

        if (! $don->isPending()) {
            return back()->withErrors(['don' => 'Impossible d\'annuler un don déjà traité.']);
        }

        $don->delete();

        return back()->with('success', 'Proposition de don annulée.');
    }
}
