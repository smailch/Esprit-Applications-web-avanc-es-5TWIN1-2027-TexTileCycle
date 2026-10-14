<?php

use App\Modules\Ateliers\Http\Controllers\Front\AtelierController;
use App\Modules\Ateliers\Http\Controllers\Front\AvisController;
use App\Modules\Ateliers\Http\Controllers\Front\DiagnosticController;
use App\Modules\Ateliers\Providers\AteliersModuleServiceProvider;
use Illuminate\Support\Facades\Route;

Route::get('/ateliers', [AtelierController::class, 'index'])->name('ateliers');
Route::get('/ateliers/carte.json', [AtelierController::class, 'map'])->name('ateliers.map');

// Diagnostic de réparation par photo (microservice ia-diagnostic) ; analyses limitées par minute et par visiteur.
Route::get('/ateliers/diagnostic', [DiagnosticController::class, 'create'])->name('ateliers.diagnostic');
Route::post('/ateliers/diagnostic', [DiagnosticController::class, 'store'])
    ->middleware('throttle:'.AteliersModuleServiceProvider::LIMITEUR_DIAGNOSTIC)
    ->name('ateliers.diagnostic.analyser');

Route::get('/ateliers/{id}', [AtelierController::class, 'show'])
    ->where('id', '[0-9a-fA-F]{24}')
    ->name('ateliers.show');

// Avis du client connecté (un seul par atelier, réservé après un rendez-vous terminé).
Route::middleware(['citizen', 'throttle:'.AteliersModuleServiceProvider::LIMITEUR_AVIS])
    ->where(['id' => '[0-9a-fA-F]{24}'])
    ->group(function () {
        Route::post('/ateliers/{id}/avis', [AvisController::class, 'store'])->name('ateliers.avis.store');
        Route::delete('/ateliers/{id}/avis', [AvisController::class, 'destroy'])->name('ateliers.avis.destroy');
    });
