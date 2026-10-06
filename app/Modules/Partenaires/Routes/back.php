<?php

use App\Modules\Partenaires\Http\Controllers\Back\PartenaireController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->prefix('partenaires')->name('partenaires.')->group(function () {
    Route::get('/', [PartenaireController::class, 'index'])->name('index');
    Route::patch('/{type}/{id}/statut', [PartenaireController::class, 'updateStatut'])->name('statut');
});
