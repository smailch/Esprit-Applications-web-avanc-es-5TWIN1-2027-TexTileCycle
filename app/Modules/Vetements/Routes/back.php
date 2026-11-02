<?php

use App\Modules\Vetements\Http\Controllers\Back\VetementController;
use Illuminate\Support\Facades\Route;

Route::get('/vetements', [VetementController::class, 'index'])->name('vetements');
Route::patch('/vetements/{id}/traiter', [VetementController::class, 'traiter'])->name('vetements.traiter');
