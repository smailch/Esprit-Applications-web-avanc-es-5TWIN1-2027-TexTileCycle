<?php

namespace App\Providers;

use App\Modules\Core\Support\AtlasIpSynchronizer;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (! AtlasIpSynchronizer::shouldRunForCurrentProcess()) {
            return;
        }

        try {
            $ip = app(AtlasIpSynchronizer::class)->sync();
            fwrite(STDERR, "MongoDB Atlas : IP {$ip} autorisée pour ce lancement.\n");
        } catch (Throwable $e) {
            fwrite(STDERR, "MongoDB Atlas : {$e->getMessage()}\n");
        }
    }
}
