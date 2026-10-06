<?php

use App\Modules\RendezVous\Http\Controllers\Front\RendezVousController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes front-office du module RendezVous
|--------------------------------------------------------------------------
| Toutes les routes sont protégées par le middleware 'citizen'.
| Le préfixe 'front.' est ajouté automatiquement par web.php.
*/
Route::middleware('citizen')->group(function () {
    // Liste des rendez-vous de l'utilisateur
    Route::get('/rendez-vous', [RendezVousController::class, 'index'])->name('rdv');

    // Formulaire de création
    Route::get('/rendez-vous/create', [RendezVousController::class, 'create'])->name('rdv.create');

    // Enregistrement d'un nouveau RDV
    Route::post('/rendez-vous', [RendezVousController::class, 'store'])->name('rdv.store');

    // Détail d'un RDV
    Route::get('/rendez-vous/{rendezVous}', [RendezVousController::class, 'show'])->name('rdv.show');

    // Formulaire de modification
    Route::get('/rendez-vous/{rendezVous}/edit', [RendezVousController::class, 'edit'])->name('rdv.edit');

    // Mise à jour d'un RDV
    Route::put('/rendez-vous/{rendezVous}', [RendezVousController::class, 'update'])->name('rdv.update');

    // Annulation d'un RDV (action spéciale PATCH)
    Route::patch('/rendez-vous/{rendezVous}/annuler', [RendezVousController::class, 'annuler'])->name('rdv.annuler');

    // Suppression d'un RDV
    Route::delete('/rendez-vous/{rendezVous}', [RendezVousController::class, 'destroy'])->name('rdv.destroy');
});
