<?php

namespace App\Modules\Ateliers\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Ateliers\Http\Controllers\Back\Concerns\GereEspaceAtelier;
use App\Modules\Ateliers\Http\Requests\UpdateMonAtelierRequest;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Ateliers\Support\HorairesFormulaire;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use Illuminate\Http\Request;

class MonAtelierController extends Controller
{
    use GereEspaceAtelier;
    use RendersBackOffice;

    public function __construct(
        private AtelierService $ateliers,
    ) {
        $this->reserverAuRoleAtelier();
    }

    public function edit(Request $request)
    {
        $atelier = $this->monAtelier($request);

        if (! $atelier) {
            return $this->vueAtelierNonConfigure();
        }

        $this->authorize('update', $atelier);

        return $this->backView('back.ateliers.espace.profil', [
            'pageTitle' => 'Mon atelier',
            'atelier' => $atelier,
            'compte' => $request->user(),
            'nbServices' => $atelier->services->count(),
            'horaires' => HorairesFormulaire::pourFormulaire(old('horaires', $atelier->horairesSemaine())),
        ]);
    }

    /**
     * validated() ne contient ni statut, ni user_id, ni note : updateOwn() n'écrit que les champs éditables.
     */
    public function update(UpdateMonAtelierRequest $request)
    {
        $atelier = $this->monAtelier($request);

        if (! $atelier) {
            return $this->redirectionAtelierNonConfigure();
        }

        $this->authorize('update', $atelier);

        $this->ateliers->updateOwn((string) $request->user()->getKey(), $request->validated());

        return redirect()
            ->route('back.ateliers.profil')
            ->with('success', 'Votre fiche atelier a été enregistrée.');
    }
}
