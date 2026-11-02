<?php

namespace App\Console\Commands;

use App\Modules\Core\Support\AtlasIpSynchronizer;
use Illuminate\Console\Command;
use Throwable;

class MongodbSyncIpCommand extends Command
{
    protected $signature = 'mongodb:sync-ip';

    protected $description = 'Autorise l\'IP publique actuelle dans MongoDB Atlas et retire l\'IP automatique précédente';

    public function handle(AtlasIpSynchronizer $synchronizer): int
    {
        try {
            $ip = $synchronizer->sync();
            $this->info("IP {$ip} autorisée sur MongoDB Atlas.");
            $this->line('Si elle vient d\'être ajoutée, Atlas peut mettre jusqu\'à une minute pour l\'activer.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            $this->newLine();
            $this->line('À faire une seule fois :');
            $this->line('1. Atlas → Access Manager → Project Access → API Keys → Create API Key');
            $this->line('2. Rôle Project Owner sur le projet TexTileCycle');
            $this->line('3. .env → ATLAS_PROJECT_ID, ATLAS_PUBLIC_KEY, ATLAS_PRIVATE_KEY');
            $this->line('Ensuite, php artisan serve, db:seed et migrate mettent l\'IP à jour tout seuls.');

            return self::FAILURE;
        }
    }
}
