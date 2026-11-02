<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Jenssegers\Mongodb\Schema\Blueprint;

/**
 * Index de la collection "passeports" : recherche par code (QR), un seul passeport par rendez-vous,
 * listes par client et par pièce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('passeports', function (Blueprint $collection) {
            $collection->unique('code');
            $collection->unique('rendez_vous_id');
            $collection->index('user_id');
            $collection->index('vetement_id');
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('passeports', function (Blueprint $collection) {
            $collection->dropIndex(['code']);
            $collection->dropIndex(['rendez_vous_id']);
            $collection->dropIndex(['user_id']);
            $collection->dropIndex(['vetement_id']);
        });
    }
};
