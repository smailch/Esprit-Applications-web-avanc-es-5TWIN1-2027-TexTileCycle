<?php

use App\Modules\Dashboard\Http\Controllers\Back\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
