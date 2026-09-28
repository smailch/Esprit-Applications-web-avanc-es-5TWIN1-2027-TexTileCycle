<?php

use App\Modules\Vetements\Http\Controllers\Front\VetementController;
use Illuminate\Support\Facades\Route;

Route::middleware('citizen')->group(function () {
    Route::get('/mes-vetements', [VetementController::class, 'index'])->name('vetements');
    Route::post('/mes-vetements', [VetementController::class, 'store'])->name('vetements.store');
    Route::patch('/mes-vetements/{vetement}/action', [VetementController::class, 'updateAction'])->name('vetements.action');
});
