<?php

namespace App\Modules\Ateliers\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Ateliers\Http\Controllers\Back\Concerns\GereEspaceAtelier;
use App\Modules\Ateliers\Http\Requests\GenererPasseportRequest;
use App\Modules\Ateliers\Models\Passeport;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Ateliers\Services\PasseportService;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use Illuminate\Http\Request;

/**
 * Espace atelier : réparations terminées, génération des passeports et impression des étiquettes QR.
 * L'atelier vient toujours du compte connecté ; un rendez-vous ou un passeport d'un autre atelier donne une 404.
 */
class MonPasseportController extends Controller
{
    use GereEspaceAtelier;
    use RendersBackOffice;

    public function __construct(
        private AtelierService $ateliers,
        private PasseportService $passeports,
    ) {
        $this->reserverAuRoleAtelier();
    }

    public function index(Request $request)
    {
        $atelier = $this->monAtelier($request);

        if (! $atelier) {
            return $this->vueAtelierNonConfigure();
        }

        $reparations = $this->passeports->reparationsDeLatelier($atelier);

        return $this->backView('back.ateliers.espace.passeports', [
            'pageTitle' => 'Mon atelier',
            'atelier' => $atelier,
            'nbServices' => $atelier->services->count(),
            'reparations' => $reparations,
            'enCours' => $this->passeports->reparationsEnCours($atelier),
            'nbCertifiees' => $reparations->filter(fn (array $ligne) => $ligne['passeport'] !== null)->count(),
        ]);
    }

    public function store(GenererPasseportRequest $request)
    {
        $atelier = $this->monAtelier($request);

        if (! $atelier) {
            return $this->redirectionAtelierNonConfigure();
        }

        $data = $request->validated();

        $passeport = $this->passeports->generer($atelier, $data['rendez_vous_id'], [
            'mot_atelier' => $data['mot_atelier'] ?? null,
            'garantie_jours' => $data['garantie_jours'] ?? 0,
        ]);

        return redirect()
            ->route('back.ateliers.passeports')
            ->with('success', "Passeport {$passeport->numeroCourt()} prêt pour « {$passeport->vetementAffiche()} » : imprimez l'étiquette et cousez-la sur la pièce.");
    }

    /** Page d'étiquette à imprimer (sans le gabarit du back office). */
    public function etiquette(Request $request, string $code)
    {
        $atelier = $this->monAtelier($request);

        if (! $atelier) {
            return $this->redirectionAtelierNonConfigure();
        }

        $passeport = $this->passeports->trouverParCode($code);
        abort_unless($passeport && (string) $passeport->atelier_id === (string) $atelier->getKey(), 404);

        return view('back.ateliers.espace.passeport-etiquette', [
            'passeport' => $passeport,
            'url' => $this->passeports->url($passeport),
        ]);
    }
}
