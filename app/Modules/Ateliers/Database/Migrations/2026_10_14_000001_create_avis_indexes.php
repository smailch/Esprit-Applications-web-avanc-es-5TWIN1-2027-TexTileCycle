<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Jenssegers\Mongodb\Schema\Blueprint;

/**
 * Index de la collection "avis" : lecture par atelier, et un seul avis par client et par atelier
 * (l'index unique empêche aussi un doublon en cas de double envoi simultané).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('avis', function (Blueprint $collection) {
            $collection->index('atelier_id');
            $collection->unique(['atelier_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('avis', function (Blueprint $collection) {
            $collection->dropIndex(['atelier_id']);
            $collection->dropIndex(['atelier_id', 'user_id']);
        });
    }
};
