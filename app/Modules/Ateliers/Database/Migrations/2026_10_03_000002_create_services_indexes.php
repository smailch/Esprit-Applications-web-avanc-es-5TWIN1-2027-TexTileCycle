<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Jenssegers\Mongodb\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('services', function (Blueprint $collection) {
            $collection->index('atelier_id');
            $collection->index('nom');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('services', function (Blueprint $collection) {
            $collection->dropIndex(['atelier_id']);
            $collection->dropIndex(['nom']);
        });
    }
};
