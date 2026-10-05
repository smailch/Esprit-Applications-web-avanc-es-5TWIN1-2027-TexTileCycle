<?php

use App\Modules\RendezVous\Http\Controllers\Back\RendezVousController;
use Illuminate\Support\Facades\Route;

/*
 * Préfixe /admin, noms back.* et middlewares auth + backoffice + backoffice.route posés par routes/web.php.
 * Le rôle atelier n'accède à back.rdv.statut que si RoleNavigationService autorise 'back.rdv.*'.
 */
Route::get('/rendez-vous', [RendezVousController::class, 'index'])->name('rdv');
Route::patch('/rendez-vous/{id}/statut', [RendezVousController::class, 'updateStatut'])
    ->where('id', '[0-9a-fA-F]{24}')
    ->name('rdv.statut');
