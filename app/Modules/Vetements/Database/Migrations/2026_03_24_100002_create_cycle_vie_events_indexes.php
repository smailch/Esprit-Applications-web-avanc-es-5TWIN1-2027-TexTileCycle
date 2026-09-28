<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Jenssegers\Mongodb\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('cycle_vie_events', function (Blueprint $collection) {
            $collection->index('vetement_id');
            $collection->index(['vetement_id', 'step_order']);
            $collection->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('cycle_vie_events', function (Blueprint $collection) {
            $collection->dropIndex(['vetement_id']);
            $collection->dropIndex(['vetement_id', 'step_order']);
            $collection->dropIndex(['occurred_at']);
        });
    }
};
