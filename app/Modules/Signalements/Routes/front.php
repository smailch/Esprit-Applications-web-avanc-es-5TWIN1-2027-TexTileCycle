<?php

use App\Modules\Signalements\Http\Controllers\Front\SignalementController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/mes-signalements', [SignalementController::class, 'index'])->name('signalements.index');
    Route::post('/signalements', [SignalementController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('signalements.store');
});
