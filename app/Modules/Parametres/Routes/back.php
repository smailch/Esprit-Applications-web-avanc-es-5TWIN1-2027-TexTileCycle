<?php

use App\Modules\Parametres\Http\Controllers\Back\ParametreController;
use Illuminate\Support\Facades\Route;

Route::get('/parametres', [ParametreController::class, 'index'])->name('parametres');
