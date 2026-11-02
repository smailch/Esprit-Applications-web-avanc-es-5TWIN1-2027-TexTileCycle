<?php

use App\Modules\Statistiques\Http\Controllers\Back\StatistiqueController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->prefix('statistiques')->name('statistiques.')->group(function () {
    Route::get('/', [StatistiqueController::class, 'index'])->name('index');
    Route::post('/consolider', [StatistiqueController::class, 'consolider'])->name('consolider');
    Route::get('/export', [StatistiqueController::class, 'export'])->name('export');
    Route::get('/rapport', [StatistiqueController::class, 'rapport'])->name('rapport');
    Route::post('/rapport', [StatistiqueController::class, 'genererRapport'])->middleware('throttle:10,1')->name('rapport.generer');
    Route::get('/rapport/telecharger', [StatistiqueController::class, 'telechargerRapport'])->name('rapport.telecharger');
});
