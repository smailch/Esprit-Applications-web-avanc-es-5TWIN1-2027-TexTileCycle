<?php

namespace App\Modules\Signalements\Console;

use App\Modules\Signalements\Services\TriageSignalementService;
use Illuminate\Console\Command;

class TrierSignalements extends Command
{
    protected $signature = 'signalements:trier
                            {--tous : Réanalyser aussi les signalements déjà triés}
                            {--limite=50 : Nombre maximum de signalements à analyser}';

    protected $description = 'Triage IA des signalements en attente (catégorie, gravité, doublons, décision suggérée)';

    public function handle(TriageSignalementService $triage): int
    {
        $nb = $triage->trierEnAttente((int) $this->option('limite'), (bool) $this->option('tous'));

        $this->info("{$nb} signalement(s) analysé(s).");

        return self::SUCCESS;
    }
}
