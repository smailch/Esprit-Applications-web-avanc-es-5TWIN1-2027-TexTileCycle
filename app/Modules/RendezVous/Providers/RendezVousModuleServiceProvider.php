<?php

namespace App\Modules\RendezVous\Providers;

use App\Modules\RendezVous\Models\RendezVous;
use App\Modules\RendezVous\Policies\RendezVousPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class RendezVousModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(app_path('Modules/RendezVous/Database/Migrations'));

        Gate::policy(RendezVous::class, RendezVousPolicy::class);
    }
}
