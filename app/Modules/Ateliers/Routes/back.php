<?php

use App\Modules\Ateliers\Http\Controllers\Back\AtelierController;
use Illuminate\Support\Facades\Route;

Route::get('/ateliers', [AtelierController::class, 'index'])->name('ateliers');
