<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(\App\Modules\Auth\Database\Seeders\AdminUserSeeder::class);

        // Module 5 — Administration, statistiques & impact
        $this->call([
            \App\Modules\Signalements\Database\Seeders\SignalementSeeder::class,
            \App\Modules\Statistiques\Database\Seeders\HistoriqueStatistiquesSeeder::class,
        ]);
    }
}
