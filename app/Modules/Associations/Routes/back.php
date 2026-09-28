<?php

use App\Modules\Associations\Http\Controllers\Back\AssociationController;
use Illuminate\Support\Facades\Route;

Route::get('/associations', [AssociationController::class, 'index'])->name('associations');
