<?php

use App\Modules\Statistiques\Http\Controllers\Back\StatistiqueController;
use Illuminate\Support\Facades\Route;

Route::get('/statistiques', [StatistiqueController::class, 'index'])->name('statistiques');
