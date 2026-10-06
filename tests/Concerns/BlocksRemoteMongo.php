<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Redirige la connexion mongodb vers un port local fermé : toute requête réelle échoue
 * en ~100 ms au lieu d'atteindre la base Atlas partagée. Les tests utilisent des modèles en mémoire.
 */
trait BlocksRemoteMongo
{
    protected function blockRemoteMongo(): void
    {
        config([
            'database.connections.mongodb.dsn' => 'mongodb://127.0.0.1:1/?serverSelectionTimeoutMS=100&connectTimeoutMS=100',
            'database.connections.mongodb.database' => 'textilecycle_tests_offline',
            'database.connections.mongodb.driver_options' => [],
        ]);

        DB::purge('mongodb');
    }
}
