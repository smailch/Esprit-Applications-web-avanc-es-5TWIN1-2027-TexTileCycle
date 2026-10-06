<?php

namespace App\Modules\Statistiques\Providers;

use App\Modules\Statistiques\Console\ConsoliderStatistiques;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class StatistiquesModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(app_path('Modules/Statistiques/Database/Migrations'));

        if ($this->app->runningInConsole()) {
            $this->commands([ConsoliderStatistiques::class]);
        }

        // Consolidation quotidienne du mois en cours (et du précédent le 1er du mois).
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('statistiques:consolider --mois=2')->dailyAt('02:00');
        });
    }
}
