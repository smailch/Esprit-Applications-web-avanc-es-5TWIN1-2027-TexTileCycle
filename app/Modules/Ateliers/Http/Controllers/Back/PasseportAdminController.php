<?php

namespace App\Modules\Ateliers\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Ateliers\Services\PasseportService;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use Illuminate\Http\Request;

/**
 * Administration des passeports de réparation (routes sous le middleware admin) :
 * liste, révocation et rétablissement. Un passeport n'est jamais supprimé, pour garder la trace.
 */
class PasseportAdminController extends Controller
{
    use RendersBackOffice;

    public function __construct(private PasseportService $passeports)
    {
    }

    public function index()
    {
        $passeports = $this->passeports->pourAdmin();

        return $this->backView('back.ateliers.passeports-admin', [
            'pageTitle' => 'Passeports de réparation',
            'passeports' => $passeports,
            'nbRevoques' => $passeports->filter->estRevoque()->count(),
        ]);
    }

    public function revocation(Request $request, string $id)
    {
        $data = $request->validate(
            ['revoquer' => ['required', 'boolean']],
            ['revoquer.required' => 'Choisissez de révoquer ou de rétablir le passeport.', 'revoquer.boolean' => 'Action invalide.']
        );

        $passeport = $this->passeports->changerRevocation($id, (bool) $data['revoquer']);

        $message = $passeport->estRevoque()
            ? "Passeport {$passeport->numeroCourt()} révoqué : sa page publique indique qu'il n'est plus valide."
            : "Passeport {$passeport->numeroCourt()} rétabli.";

        return redirect()->route('back.ateliers.passeports.admin')->with('success', $message);
    }
}
