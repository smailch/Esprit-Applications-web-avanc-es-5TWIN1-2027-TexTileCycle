<?php

use App\Modules\RendezVous\Http\Controllers\Back\RendezVousController;
use Illuminate\Support\Facades\Route;

Route::get('/rendez-vous', [RendezVousController::class, 'index'])->name('rdv');
Route::patch('/rendez-vous/{id}/statut', [RendezVousController::class, 'updateStatut'])->name('rdv.statut');
