<?php

use App\Modules\Vetements\Http\Controllers\Back\VetementController;
use Illuminate\Support\Facades\Route;

Route::get('/vetements', [VetementController::class, 'index'])->name('vetements');
