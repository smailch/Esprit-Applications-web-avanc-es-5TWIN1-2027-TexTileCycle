<?php

use App\Modules\RendezVous\Http\Controllers\Back\RendezVousController;
use Illuminate\Support\Facades\Route;

Route::get('/rendez-vous', [RendezVousController::class, 'index'])->name('rdv');
