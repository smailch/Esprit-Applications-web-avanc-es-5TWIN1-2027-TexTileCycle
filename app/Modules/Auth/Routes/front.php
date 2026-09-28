<?php

use App\Modules\Auth\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/connexion', [AuthController::class, 'login'])->name('login');
Route::get('/inscription', [AuthController::class, 'register'])->name('register');
