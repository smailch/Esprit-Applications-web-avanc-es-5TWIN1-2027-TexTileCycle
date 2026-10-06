<?php

use App\Modules\Signalements\Http\Controllers\Front\SignalementController;
use Illuminate\Support\Facades\Route;

Route::post('/signalements', [SignalementController::class, 'store'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('signalements.store');
