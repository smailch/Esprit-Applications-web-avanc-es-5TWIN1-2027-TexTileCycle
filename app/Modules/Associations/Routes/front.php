<?php

use App\Modules\Associations\Http\Controllers\Front\AssociationController;
use Illuminate\Support\Facades\Route;

Route::get('/associations', [AssociationController::class, 'index'])->name('associations');
