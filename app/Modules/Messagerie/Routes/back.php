<?php

use App\Modules\Messagerie\Http\Controllers\Back\ConversationController;
use Illuminate\Support\Facades\Route;

$objectId = '[0-9a-fA-F]{24}';

Route::middleware('admin')->prefix('messagerie')->name('messagerie.')->group(function () use ($objectId) {
    Route::get('/', [ConversationController::class, 'index'])->name('index');
    Route::get('/non-lues', [ConversationController::class, 'nonLues'])->name('non-lues');
    Route::get('/nouvelle', [ConversationController::class, 'create'])->name('create');
    Route::post('/', [ConversationController::class, 'store'])->name('store');
    Route::get('/{conversation}', [ConversationController::class, 'show'])->where('conversation', $objectId)->name('show');
    Route::get('/{conversation}/modifier', [ConversationController::class, 'edit'])->where('conversation', $objectId)->name('edit');
    Route::put('/{conversation}', [ConversationController::class, 'update'])->where('conversation', $objectId)->name('update');
    Route::delete('/{conversation}', [ConversationController::class, 'destroy'])->where('conversation', $objectId)->name('destroy');
    Route::get('/{conversation}/messages', [ConversationController::class, 'messages'])->where('conversation', $objectId)->name('messages.index');
    Route::post('/{conversation}/messages', [ConversationController::class, 'envoyer'])->where('conversation', $objectId)
        ->middleware('throttle:30,1')->name('messages.store');
});
