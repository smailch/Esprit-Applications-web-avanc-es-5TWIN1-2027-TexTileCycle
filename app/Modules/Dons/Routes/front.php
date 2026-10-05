<?php

use App\Modules\Dons\Http\Controllers\Front\DonController;
use Illuminate\Support\Facades\Route;

Route::middleware('citizen')->group(function () {
    Route::get('/dons',        [DonController::class, 'index'])->name('dons');
    Route::post('/dons',       [DonController::class, 'store'])->name('dons.store');
    Route::put('/dons/{id}',   [DonController::class, 'update'])->name('dons.update');
    Route::delete('/dons/{id}',[DonController::class, 'destroy'])->name('dons.destroy');
});
