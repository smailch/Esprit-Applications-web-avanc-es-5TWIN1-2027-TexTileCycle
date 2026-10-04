<?php

use App\Modules\RendezVous\Http\Controllers\Front\RendezVousController;
use Illuminate\Support\Facades\Route;

Route::middleware('citizen')->group(function () {
    Route::get('/rendez-vous', [RendezVousController::class, 'index'])->name('rdv');
    Route::post('/rendez-vous', [RendezVousController::class, 'store'])->name('rdv.store');
});
