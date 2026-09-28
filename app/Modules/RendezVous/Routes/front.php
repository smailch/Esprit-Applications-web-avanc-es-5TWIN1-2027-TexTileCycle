<?php

use App\Modules\RendezVous\Http\Controllers\Front\RendezVousController;
use Illuminate\Support\Facades\Route;

Route::get('/rendez-vous', [RendezVousController::class, 'index'])->name('rdv');
