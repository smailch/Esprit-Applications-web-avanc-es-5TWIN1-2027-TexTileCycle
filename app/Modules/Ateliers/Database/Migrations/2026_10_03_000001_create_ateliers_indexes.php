<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Jenssegers\Mongodb\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('ateliers', function (Blueprint $collection) {
            $collection->unique('user_id');
            $collection->index('statut');
            $collection->index('note_moyenne');
            $collection->index('ville');
            $collection->geospatial('location', '2dsphere');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('ateliers', function (Blueprint $collection) {
            $collection->dropIndex(['user_id']);
            $collection->dropIndex(['statut']);
            $collection->dropIndex(['note_moyenne']);
            $collection->dropIndex(['ville']);
            $collection->dropIndex(['location' => '2dsphere']);
        });
    }
};
