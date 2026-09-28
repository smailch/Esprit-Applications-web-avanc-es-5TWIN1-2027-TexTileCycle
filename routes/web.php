<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Front Office — routes chargées automatiquement par module
| Chaque module expose : app/Modules/{Module}/Routes/front.php
|--------------------------------------------------------------------------
*/
Route::name('front.')->group(function () {
    foreach (glob(app_path('Modules/*/Routes/front.php')) ?: [] as $routeFile) {
        require $routeFile;
    }
});

/*
|--------------------------------------------------------------------------
| Back Office — routes chargées automatiquement par module
| Chaque module expose : app/Modules/{Module}/Routes/back.php
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('back.')->group(function () {
    foreach (glob(app_path('Modules/*/Routes/back.php')) ?: [] as $routeFile) {
        require $routeFile;
    }
});
