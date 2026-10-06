<?php

use App\Modules\Statistiques\Http\Controllers\Back\StatistiqueController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->prefix('statistiques')->name('statistiques.')->group(function () {
    Route::get('/', [StatistiqueController::class, 'index'])->name('index');
    Route::post('/consolider', [StatistiqueController::class, 'consolider'])->name('consolider');
    Route::get('/export', [StatistiqueController::class, 'export'])->name('export');
});
