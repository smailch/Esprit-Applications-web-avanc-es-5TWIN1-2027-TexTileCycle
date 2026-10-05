<?php

use App\Modules\Signalements\Http\Controllers\Back\SignalementController;
use Illuminate\Support\Facades\Route;

Route::get('/signalements', [SignalementController::class, 'index'])->name('signalements');
