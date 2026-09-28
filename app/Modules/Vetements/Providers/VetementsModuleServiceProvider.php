<?php

namespace App\Modules\Vetements\Providers;

use Illuminate\Support\ServiceProvider;

class VetementsModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(app_path('Modules/Vetements/Database/Migrations'));
    }
}
