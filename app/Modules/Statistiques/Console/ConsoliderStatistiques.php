<?php

namespace App\Modules\Statistiques\Console;

use App\Modules\Statistiques\Services\ConsolidationService;
use Illuminate\Console\Command;

class ConsoliderStatistiques extends Command
{
    protected $signature = 'statistiques:consolider {--mois=1 : Nombre de mois à (re)consolider, mois courant inclus}';

    protected $description = 'Consolide les statistiques et l\'impact écologique mensuels (module 5)';

    public function handle(ConsolidationService $consolidation): int
    {
        $nb = $consolidation->consolider((int) $this->option('mois'));

        $this->info("{$nb} mois consolidé(s) dans les collections statistiques et impact_ecologique.");

        return self::SUCCESS;
    }
}
