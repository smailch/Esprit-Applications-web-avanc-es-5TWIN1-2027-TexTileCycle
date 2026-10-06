<?php

use App\Modules\Signalements\Http\Controllers\Back\SignalementController;
use Illuminate\Support\Facades\Route;

$objectId = '[0-9a-fA-F]{24}';

Route::middleware('admin')->prefix('signalements')->name('signalements.')->group(function () use ($objectId) {
    Route::get('/', [SignalementController::class, 'index'])->name('index');
    Route::get('/creer', [SignalementController::class, 'create'])->name('create');
    Route::post('/', [SignalementController::class, 'store'])->name('store');
    Route::get('/{signalement}', [SignalementController::class, 'show'])->where('signalement', $objectId)->name('show');
    Route::get('/{signalement}/modifier', [SignalementController::class, 'edit'])->where('signalement', $objectId)->name('edit');
    Route::put('/{signalement}', [SignalementController::class, 'update'])->where('signalement', $objectId)->name('update');
    Route::patch('/{signalement}/moderation', [SignalementController::class, 'moderer'])->where('signalement', $objectId)->name('moderer');
    Route::delete('/{signalement}', [SignalementController::class, 'destroy'])->where('signalement', $objectId)->name('destroy');
});
