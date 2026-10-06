<?php

namespace App\Modules\Signalements\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Modules\Signalements\Http\Requests\StoreSignalementRequest;
use App\Modules\Signalements\Services\SignalementService;

class SignalementController extends Controller
{
    public function __construct(
        private SignalementService $signalements
    ) {
    }

    public function store(StoreSignalementRequest $request)
    {
        $this->signalements->create($request->validated(), $request->user());

        return back()->with('success', 'Merci, votre signalement a été transmis à l\'équipe de modération.');
    }
}
