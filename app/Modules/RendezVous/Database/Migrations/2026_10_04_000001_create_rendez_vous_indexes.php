<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Jenssegers\Mongodb\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('rendez_vous', function (Blueprint $collection) {
            $collection->index(['atelier_id', 'date']);
            $collection->index('user_id');
            $collection->index('statut');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('rendez_vous', function (Blueprint $collection) {
            $collection->dropIndex(['atelier_id', 'date']);
            $collection->dropIndex(['user_id']);
            $collection->dropIndex(['statut']);
        });
    }
};
