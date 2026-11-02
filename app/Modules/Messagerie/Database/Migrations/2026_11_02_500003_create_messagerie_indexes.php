<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Jenssegers\Mongodb\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('conversations', function (Blueprint $collection) {
            $collection->index('user_id');
            $collection->index('dernier_message_at');
            $collection->index('non_lus_admin');
        });

        Schema::connection('mongodb')->table('messages', function (Blueprint $collection) {
            $collection->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('conversations', function (Blueprint $collection) {
            $collection->dropIndex(['user_id']);
            $collection->dropIndex(['dernier_message_at']);
            $collection->dropIndex(['non_lus_admin']);
        });

        Schema::connection('mongodb')->table('messages', function (Blueprint $collection) {
            $collection->dropIndex(['conversation_id', 'created_at']);
        });
    }
};
