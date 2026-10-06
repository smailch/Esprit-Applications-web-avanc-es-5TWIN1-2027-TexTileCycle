<?php

namespace App\Modules\Ateliers\Database\Seeders;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Auth\Models\User;
use Illuminate\Database\Seeder;

/**
 * Supprime uniquement les données créées par AtelierSeeder :
 * comptes *@textilecycle.test, leurs ateliers et les services de ces ateliers.
 */
class PurgeAtelierTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $userIds = User::where('email', 'like', '%'.AtelierSeeder::EMAIL_DOMAIN)
            ->get()
            ->map(fn (User $user) => (string) $user->getKey())
            ->all();

        $atelierIds = Atelier::whereIn('user_id', $userIds)
            ->get()
            ->map(fn (Atelier $atelier) => (string) $atelier->getKey())
            ->all();

        $services = Service::whereIn('atelier_id', $atelierIds)->delete();
        $ateliers = Atelier::whereIn('_id', $atelierIds)->delete();
        $users = User::whereIn('_id', $userIds)->delete();

        $this->command?->info("Supprimés : {$users} comptes, {$ateliers} ateliers, {$services} services.");
    }
}
