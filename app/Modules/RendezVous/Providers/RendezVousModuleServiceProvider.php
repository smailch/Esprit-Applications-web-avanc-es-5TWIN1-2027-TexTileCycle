<?php

namespace App\Modules\RendezVous\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Service provider du module RendezVous.
 * Charge les migrations du module.
 */
class RendezVousModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(app_path('Modules/RendezVous/Database/Migrations'));
    }
}
