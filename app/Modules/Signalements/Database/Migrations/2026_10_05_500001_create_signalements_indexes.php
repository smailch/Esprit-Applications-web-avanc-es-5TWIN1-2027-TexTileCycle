<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Jenssegers\Mongodb\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('signalements', function (Blueprint $collection) {
            $collection->index('statut');
            $collection->index('user_id');
            $collection->index(['cible_type', 'cible_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('signalements', function (Blueprint $collection) {
            $collection->dropIndex(['statut']);
            $collection->dropIndex(['user_id']);
            $collection->dropIndex(['cible_type', 'cible_id']);
        });
    }
};
