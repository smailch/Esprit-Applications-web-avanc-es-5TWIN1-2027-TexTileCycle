<?php

use App\Modules\Dons\Http\Controllers\Front\DonController;
use Illuminate\Support\Facades\Route;

Route::get('/dons', [DonController::class, 'index'])->name('dons');
