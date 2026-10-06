<?php

use App\Modules\Signalements\Http\Controllers\Back\SignalementController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/signalements', [SignalementController::class, 'index'])->name('signalements.index');
    Route::post('/signalements', [SignalementController::class, 'store'])->name('signalements.store');
    Route::put('/signalements/{signalement}', [SignalementController::class, 'update'])->name('signalements.update');
    Route::patch('/signalements/{signalement}/moderation', [SignalementController::class, 'moderer'])->name('signalements.moderer');
    Route::delete('/signalements/{signalement}', [SignalementController::class, 'destroy'])->name('signalements.destroy');
});
