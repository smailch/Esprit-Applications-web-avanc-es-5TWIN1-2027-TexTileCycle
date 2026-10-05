<?php

use App\Modules\Dons\Http\Controllers\Back\DonController;
use Illuminate\Support\Facades\Route;

Route::prefix('dons')->name('dons')->group(function () {
    Route::get('/',               [DonController::class, 'index'])->name('');
    Route::post('/{id}/accepter', [DonController::class, 'accept'])->name('.accept');
    Route::post('/{id}/refuser',  [DonController::class, 'refuse'])->name('.refuse');
    Route::delete('/{id}',        [DonController::class, 'destroy'])->name('.destroy');
});
