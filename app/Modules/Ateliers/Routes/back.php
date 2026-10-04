<?php

use App\Modules\Ateliers\Http\Controllers\Back\AtelierController;
use App\Modules\Ateliers\Http\Controllers\Back\MonAtelierController;
use App\Modules\Ateliers\Http\Controllers\Back\MonServiceController;
use Illuminate\Support\Facades\Route;

/*
 * Préfixe /admin, noms back.* et middlewares auth + backoffice + backoffice.route posés par routes/web.php.
 * L'index redirige le rôle atelier vers son espace ; la gestion est réservée à l'admin.
 */
$atelierObjectId = '[0-9a-fA-F]{24}';

Route::get('/ateliers', [AtelierController::class, 'index'])->name('ateliers');

// Espace du rôle atelier (vérification du rôle dans les contrôleurs) : déclaré avant les routes admin à {id}.
Route::prefix('ateliers')->name('ateliers.')->group(function () use ($atelierObjectId) {
    Route::get('/mon-profil', [MonAtelierController::class, 'edit'])->name('profil');
    Route::put('/mon-profil', [MonAtelierController::class, 'update'])->name('profil.update');

    Route::get('/mes-services', [MonServiceController::class, 'index'])->name('services');
    Route::post('/mes-services', [MonServiceController::class, 'store'])->name('services.store');
    Route::put('/mes-services/{id}', [MonServiceController::class, 'update'])->where('id', $atelierObjectId)->name('services.update');
    Route::delete('/mes-services/{id}', [MonServiceController::class, 'destroy'])->where('id', $atelierObjectId)->name('services.destroy');
});

Route::middleware('admin')->prefix('ateliers')->name('ateliers.')->group(function () use ($atelierObjectId) {
    Route::get('/creer', [AtelierController::class, 'create'])->name('create');
    Route::post('/', [AtelierController::class, 'store'])->name('store');
    Route::get('/{id}/modifier', [AtelierController::class, 'edit'])->where('id', $atelierObjectId)->name('edit');
    Route::put('/{id}', [AtelierController::class, 'update'])->where('id', $atelierObjectId)->name('update');
    Route::delete('/{id}', [AtelierController::class, 'destroy'])->where('id', $atelierObjectId)->name('destroy');
    Route::patch('/{id}/statut', [AtelierController::class, 'updateStatut'])->where('id', $atelierObjectId)->name('statut');
});
