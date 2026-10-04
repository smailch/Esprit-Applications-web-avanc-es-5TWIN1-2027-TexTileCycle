<?php

use App\Modules\Ateliers\Http\Controllers\Front\AtelierController;
use Illuminate\Support\Facades\Route;

Route::get('/ateliers', [AtelierController::class, 'index'])->name('ateliers');
Route::get('/ateliers/carte.json', [AtelierController::class, 'map'])->name('ateliers.map');
Route::get('/ateliers/{id}', [AtelierController::class, 'show'])
    ->where('id', '[0-9a-fA-F]{24}')
    ->name('ateliers.show');
