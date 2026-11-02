<?php

namespace App\Modules\Partenaires\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use App\Modules\Partenaires\Services\PartenaireService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartenaireController extends Controller
{
    use RendersBackOffice;

    public function __construct(
        private PartenaireService $partenaires
    ) {
    }

    public function index(Request $request)
    {
        $type = array_key_exists($request->query('type'), PartenaireService::TYPES)
            ? $request->query('type')
            : 'atelier';

        return $this->backView('back.partenaires.index', [
            'pageTitle' => 'Validation des partenaires',
            'type' => $type,
            'types' => PartenaireService::TYPES,
            'statut' => $request->query('statut'),
            'search' => $request->query('q'),
            'statuts' => PartenaireService::STATUTS,
            'counts' => $this->partenaires->countsByStatut($type),
            'partenaires' => $this->partenaires->list($type, $request->query('statut'), $request->query('q')),
        ]);
    }

    public function updateStatut(Request $request, string $type, string $id)
    {
        abort_unless(array_key_exists($type, PartenaireService::TYPES), 404);

        $data = $request->validate([
            'statut' => ['required', Rule::in(PartenaireService::STATUTS)],
        ]);

        if (! $this->partenaires->changerStatut($type, $id, $data['statut'])) {
            return back()->withErrors(['partenaire' => 'Partenaire introuvable.']);
        }

        $message = match ($data['statut']) {
            PartenaireService::STATUT_ACTIF => 'Partenaire validé : le compte responsable peut se connecter et la fiche est visible par les citoyens.',
            PartenaireService::STATUT_SUSPENDU => 'Partenaire suspendu : le compte responsable ne peut plus se connecter.',
            default => 'Partenaire remis en attente : le compte responsable est de nouveau inactif.',
        };

        return back()->with('success', $message);
    }
}
