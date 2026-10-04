<?php

namespace App\Modules\Ateliers\Providers;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Ateliers\Policies\AtelierPolicy;
use App\Modules\Ateliers\Policies\ServicePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AteliersModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(app_path('Modules/Ateliers/Database/Migrations'));

        Gate::policy(Atelier::class, AtelierPolicy::class);
        Gate::policy(Service::class, ServicePolicy::class);
    }
}
