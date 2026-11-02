<?php

namespace App\Modules\Ateliers\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Modules\Ateliers\Http\Requests\AvisRequest;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Ateliers\Services\AvisService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Publication, modification et suppression de l'avis du client connecté sur un atelier actif.
 * Toujours renvoyé sur la section Avis de la fiche (#avis).
 */
class AvisController extends Controller
{
    public function __construct(
        private AtelierService $ateliers,
        private AvisService $avis
    ) {
    }

    public function store(AvisRequest $request, string $id): RedirectResponse
    {
        $atelier = $this->ateliers->findActifOrFail($id);
        $user = $request->user();

        if (! $this->avis->peutDonnerAvis($user, $atelier)) {
            return $this->versLesAvis($id)->withErrors(
                ['note' => 'Vous pourrez donner votre avis après une réparation terminée chez cet atelier.'],
                AvisService::BAG
            );
        }

        $existait = $this->avis->avisDe($user, $atelier) !== null;
        $this->avis->enregistrer($user, $atelier, $request->validated());

        return $this->versLesAvis($id)->with(
            'avis_succes',
            $existait ? 'Votre avis a été mis à jour.' : 'Merci ! Votre avis est publié.'
        );
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $atelier = $this->ateliers->findActifOrFail($id);

        $supprime = $this->avis->supprimer($request->user(), $atelier);

        return $this->versLesAvis($id)->with('avis_succes', $supprime ? 'Votre avis a été supprimé.' : 'Vous n\'aviez pas d\'avis sur cet atelier.');
    }

    private function versLesAvis(string $id): RedirectResponse
    {
        return redirect()->to(route('front.ateliers.show', ['id' => $id]).'#avis');
    }
}
