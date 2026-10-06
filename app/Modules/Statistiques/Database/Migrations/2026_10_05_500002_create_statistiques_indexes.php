<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Jenssegers\Mongodb\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['statistiques', 'impact_ecologique'] as $collection) {
            Schema::connection('mongodb')->table($collection, function (Blueprint $c) {
                $c->unique('periode');
            });
        }
    }

    public function down(): void
    {
        foreach (['statistiques', 'impact_ecologique'] as $collection) {
            Schema::connection('mongodb')->table($collection, function (Blueprint $c) {
                $c->dropIndex(['periode']);
            });
        }
    }
};
