<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Jenssegers\Mongodb\Schema\Blueprint;

/**
 * Crée les index MongoDB sur la collection rendez_vous
 * pour optimiser les requêtes fréquentes (par utilisateur, par date, par statut).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('rendez_vous', function (Blueprint $collection) {
            $collection->index('user_id');
            $collection->index('statut');
            $collection->index(['user_id', 'date_rdv']);
            $collection->index(['user_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('rendez_vous', function (Blueprint $collection) {
            $collection->dropIndex(['user_id']);
            $collection->dropIndex(['statut']);
            $collection->dropIndex(['user_id', 'date_rdv']);
            $collection->dropIndex(['user_id', 'statut']);
        });
    }
};
