<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Jenssegers\Mongodb\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('vetements', function (Blueprint $collection) {
            $collection->index('user_id');
            $collection->index('status');
            $collection->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('vetements', function (Blueprint $collection) {
            $collection->dropIndex(['user_id']);
            $collection->dropIndex(['status']);
            $collection->dropIndex(['user_id', 'created_at']);
        });
    }
};
