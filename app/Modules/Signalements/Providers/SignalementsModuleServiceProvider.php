<?php

namespace App\Modules\Signalements\Providers;

use App\Modules\Signalements\Models\Signalement;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class SignalementsModuleServiceProvider extends ServiceProvider
{
    /**
     * Modèles Eloquent candidats pour la relation polymorphe morphTo().
     * Seuls ceux déjà livrés par les autres modules sont enregistrés.
     */
    private const MORPH_CANDIDATES = [
        'Vetement' => 'App\Modules\Vetements\Models\Vetement',
        'Atelier' => 'App\Modules\Ateliers\Models\Atelier',
        'Association' => 'App\Modules\Associations\Models\Association',
        'Don' => 'App\Modules\Dons\Models\Don',
        'User' => 'App\Modules\Auth\Models\User',
        'Message' => 'App\Modules\Messagerie\Models\Message',
    ];

    public function boot(): void
    {
        $this->loadMigrationsFrom(app_path('Modules/Signalements/Database/Migrations'));

        if ($this->app->runningInConsole()) {
            $this->commands([\App\Modules\Signalements\Console\TrierSignalements::class]);
        }

        Relation::morphMap(array_filter(
            array_intersect_key(self::MORPH_CANDIDATES, Signalement::CIBLES),
            fn (string $class) => class_exists($class)
        ));
    }
}
