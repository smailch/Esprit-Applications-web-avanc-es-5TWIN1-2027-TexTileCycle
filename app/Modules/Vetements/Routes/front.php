<?php

use App\Modules\Vetements\Http\Controllers\Front\VetementController;
use Illuminate\Support\Facades\Route;

Route::get('/mes-vetements', [VetementController::class, 'index'])->name('vetements');
