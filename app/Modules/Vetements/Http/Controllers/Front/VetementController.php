<?php

namespace App\Modules\Vetements\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Modules\Vetements\Http\Requests\StoreVetementRequest;
use App\Modules\Vetements\Http\Requests\UpdateIntendedActionRequest;
use App\Modules\Vetements\Models\Vetement;
use App\Modules\Vetements\Services\VetementService;
use Illuminate\Http\RedirectResponse;

class VetementController extends Controller
{
    public function __construct(
        private VetementService $vetements
    ) {
    }

    public function index()
    {
        $vetements = $this->vetements->listForOwner(auth()->user());

        return view('front.vetements', compact('vetements'));
    }

    public function store(StoreVetementRequest $request): RedirectResponse
    {
        $data = $request->validated();
        unset($data['photo']);

        if ($request->hasFile('photo')) {
            $data['image_path'] = $request->file('photo')->store('vetements', 'public');
        }

        $vetement = $this->vetements->declare(auth()->user(), $data);

        $message = '« '.$vetement->displayName().' » a été déclaré — parcours '.$vetement->intendedActionLabel().'.';

        return $this->redirectApresParcours($vetement, $message);
    }

    public function updateAction(UpdateIntendedActionRequest $request, string $vetement): RedirectResponse
    {
        $model = $this->vetements->findOwnedByUser($vetement, auth()->user());

        if (! $model) {
            abort(404);
        }

        $model = $this->vetements->applyIntendedAction($model, $request->validated('intended_action'));

        return $this->redirectApresParcours($model, 'Parcours mis à jour : '.$model->intendedActionLabel().'.');
    }

    private function redirectApresParcours(Vetement $vetement, string $message): RedirectResponse
    {
        if ($vetement->intended_action === Vetement::ACTION_REPARATION) {
            return redirect()
                ->route('front.ateliers', ['vetement_id' => (string) $vetement->getKey()])
                ->with('success', $message.' Choisissez un atelier : le rendez-vous sera prérempli.');
        }

        return redirect()
            ->route('front.vetements')
            ->with('success', $message);
    }
}
