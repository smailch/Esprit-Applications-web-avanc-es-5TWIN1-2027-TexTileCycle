<?php

use App\Modules\Messagerie\Http\Controllers\Front\MessagerieController;
use Illuminate\Support\Facades\Route;

$objectId = '[0-9a-fA-F]{24}';

/*
 * Messages entre un utilisateur (citoyen, atelier, association) et l'équipe d'administration.
 * Les droits sur chaque conversation sont vérifiés par ConversationPolicy.
 */
Route::middleware('auth')->prefix('messages')->name('messagerie.')->group(function () use ($objectId) {
    Route::get('/', [MessagerieController::class, 'index'])->name('index');
    Route::get('/non-lues', [MessagerieController::class, 'nonLues'])->name('non-lues');
    Route::get('/nouveau', [MessagerieController::class, 'create'])->name('create');
    Route::post('/', [MessagerieController::class, 'store'])->middleware('throttle:10,1')->name('store');
    Route::get('/{conversation}', [MessagerieController::class, 'show'])->where('conversation', $objectId)->name('show');
    Route::get('/{conversation}/messages', [MessagerieController::class, 'messages'])->where('conversation', $objectId)->name('messages.index');
    Route::post('/{conversation}/messages', [MessagerieController::class, 'envoyer'])->where('conversation', $objectId)
        ->middleware('throttle:30,1')->name('messages.store');
});
