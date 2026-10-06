<?php

namespace App\Modules\Signalements\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Modules\Signalements\Http\Requests\StoreSignalementRequest;
use App\Modules\Signalements\Models\Signalement;
use App\Modules\Signalements\Services\SignalementService;
use Illuminate\Http\Request;

class SignalementController extends Controller
{
    public function __construct(
        private SignalementService $signalements
    ) {
    }

    /** « Mes signalements » : suivi par le citoyen de ses signalements. */
    public function index(Request $request)
    {
        return view('front.signalements', [
            'signalements' => Signalement::with('cible')
                ->where('user_id', (string) $request->user()->getKey())
                ->orderBy('created_at', 'desc')
                ->paginate(10),
        ]);
    }

    public function store(StoreSignalementRequest $request)
    {
        // L'auteur est toujours l'utilisateur connecté : un éventuel user_id envoyé est ignoré.
        $this->signalements->create($request->safe()->except('user_id'), $request->user());

        return back()->with('success', 'Merci, votre signalement a été transmis à l\'équipe de modération. Suivez-le dans « Mes signalements ».');
    }
}
