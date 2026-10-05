<?php

namespace App\Modules\Associations\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Associations\Models\Association;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use Illuminate\Http\Request;

class AssociationController extends Controller
{
    use RendersBackOffice;

    /** Liste de toutes les associations (admin/association). */
    public function index(Request $request)
    {
        $statut = $request->get('statut');
        $query = Association::with('owner')->orderBy('created_at', 'desc');

        if ($statut && in_array($statut, Association::STATUTS, true)) {
            $query->where('statut', $statut);
        }

        $associations = $query->get();

        return $this->backView('back.associations.index', [
            'pageTitle' => 'Associations',
            'associations' => $associations,
            'statuts' => Association::STATUTS,
            'statut' => $statut,
        ]);
    }

    /** Formulaire création. */
    public function create()
    {
        return $this->backView('back.associations.form', [
            'pageTitle' => 'Nouvelle association',
            'association' => null,
            'typesTextile' => Association::TYPES_TEXTILE,
            'tailles' => Association::TAILLES,
        ]);
    }

    /** Enregistrement création. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string|max:150',
            'description' => 'required|string',
            'adresse' => 'required|string|max:255',
            'telephone' => 'nullable|string|max:30',
            'statut' => 'required|in:' . implode(',', Association::STATUTS),
            'besoins' => 'nullable|array',
            'besoins.*.type' => 'required|string',
            'besoins.*.taille' => 'nullable|string',
            'besoins.*.quantite' => 'nullable|integer|min:1',
        ]);

        $data['besoins'] = array_values(array_map(function ($b) {
            if (isset($b['quantite']) && $b['quantite'] !== '') {
                $b['quantite'] = (int) $b['quantite'];
            }
            return $b;
        }, array_filter($data['besoins'] ?? [])));
        $data['user_id'] = auth()->id();

        Association::create($data);

        return redirect()->route('back.associations')->with('success', 'Association créée avec succès.');
    }

    /** Formulaire édition. */
    public function edit(string $id)
    {
        $association = Association::findOrFail($id);

        return $this->backView('back.associations.form', [
            'pageTitle' => 'Modifier l\'association',
            'association' => $association,
            'typesTextile' => Association::TYPES_TEXTILE,
            'tailles' => Association::TAILLES,
        ]);
    }

    /** Enregistrement mise à jour. */
    public function update(Request $request, string $id)
    {
        $association = Association::findOrFail($id);

        $data = $request->validate([
            'nom' => 'required|string|max:150',
            'description' => 'required|string',
            'adresse' => 'required|string|max:255',
            'telephone' => 'nullable|string|max:30',
            'statut' => 'required|in:' . implode(',', Association::STATUTS),
            'besoins' => 'nullable|array',
            'besoins.*.type' => 'required|string',
            'besoins.*.taille' => 'nullable|string',
            'besoins.*.quantite' => 'nullable|integer|min:1',
        ]);

        $data['besoins'] = array_values(array_map(function ($b) {
            if (isset($b['quantite']) && $b['quantite'] !== '') {
                $b['quantite'] = (int) $b['quantite'];
            }
            return $b;
        }, array_filter($data['besoins'] ?? [])));

        $association->update($data);

        return redirect()->route('back.associations')->with('success', 'Association mise à jour.');
    }

    /** Suppression. */
    public function destroy(string $id)
    {
        $association = Association::findOrFail($id);
        $association->delete();

        return redirect()->route('back.associations')->with('success', 'Association supprimée.');
    }
}
