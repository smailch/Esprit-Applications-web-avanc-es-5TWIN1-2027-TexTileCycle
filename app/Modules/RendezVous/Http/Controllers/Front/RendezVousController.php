<?php

namespace App\Modules\RendezVous\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\RendezVous\Http\Requests\StoreRendezVousRequest;
use App\Modules\RendezVous\Services\RendezVousService;
use Illuminate\Http\Request;

class RendezVousController extends Controller
{
    public function __construct(private RendezVousService $rendezVous)
    {
    }

    /**
     * Formulaire ouvert depuis un atelier (?atelier=, ?service= facultatif) : l'atelier est relu
     * en base (actif uniquement) et seuls ses services sont proposés.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user->isCitoyen()) {
            return redirect()
                ->route('front.ateliers')
                ->with('info', 'La prise de rendez-vous est réservée aux comptes citoyens.');
        }

        $atelierId = $request->query('atelier');

        if (! is_string($atelierId) || $atelierId === '') {
            return redirect()
                ->route('front.ateliers')
                ->with('info', "Choisissez d'abord un atelier, puis cliquez sur « Prendre RDV ».");
        }

        $atelier = $this->rendezVous->atelierReservable($atelierId);

        if (! $atelier) {
            return redirect()
                ->route('front.ateliers')
                ->with('info', "Cet atelier n'est pas disponible à la réservation. Choisissez-en un autre.");
        }

        $serviceDemande = $request->query('service');
        $preselection = is_string($serviceDemande)
            ? $atelier->services->first(fn (Service $s) => (string) $s->getKey() === $serviceDemande)
            : null;

        return view('front.rdv', [
            'atelier' => $atelier,
            'services' => $atelier->services,
            'serviceSelectionne' => (string) old('service', $preselection ? (string) $preselection->getKey() : ''),
            'vetements' => $this->rendezVous->vetementsReparables((string) $user->getKey()),
            'semaine' => $atelier->horairesSemaine(),
            'dateMin' => Atelier::maintenant()->format('Y-m-d'),
        ]);
    }

    public function store(StoreRendezVousRequest $request)
    {
        $demande = $request->demande();

        $rdv = $this->rendezVous->creer(
            (string) $request->user()->getKey(),
            $demande['atelier'],
            $demande['service'],
            $demande['vetement'],
            $request->validated()
        );

        return redirect()
            ->route('front.ateliers.show', ['id' => (string) $demande['atelier']->getKey()])
            ->with('success', sprintf(
                "Votre demande de rendez-vous du %s à %s chez « %s » a été envoyée. L'atelier doit encore la confirmer.",
                mb_strtolower($rdv->dateFormatee()),
                $rdv->heure,
                $demande['atelier']->nom
            ));
    }
}
