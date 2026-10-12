<?php

namespace App\Modules\Ateliers\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Modules\Ateliers\Exceptions\DiagnosticIndisponibleException;
use App\Modules\Ateliers\Http\Requests\DiagnosticPhotoRequest;
use App\Modules\Ateliers\Services\DiagnosticService;

class DiagnosticController extends Controller
{
    public function __construct(private DiagnosticService $diagnostic)
    {
    }

    public function create()
    {
        return view('front.diagnostic', ['resultat' => null, 'texte' => null]);
    }

    /**
     * Avec JavaScript (js/diagnostic.js), la page envoie la photo sans se recharger et reçoit en JSON
     * le résultat déjà mis en forme (vue partielle), ou les erreurs de validation (422, réponse de la FormRequest).
     * Sans JavaScript, envoi classique : la page du résultat est rendue directement.
     * Rien n'est enregistré : la photo est supprimée en fin de requête.
     */
    public function store(DiagnosticPhotoRequest $request)
    {
        $texte = $request->validated('texte');

        try {
            $resultat = $this->diagnostic->diagnostiquer($request->file('photo'), $texte, $request->validated('defaut'), $request->position());
        } catch (DiagnosticIndisponibleException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 503);
            }

            return redirect()
                ->route('front.ateliers.diagnostic')
                ->withInput($request->only('texte'))
                ->with('diagnostic_erreur', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('front.partials.diagnostic-resultat', ['resultat' => $resultat])->render(),
            ]);
        }

        return view('front.diagnostic', ['resultat' => $resultat, 'texte' => $texte]);
    }
}
