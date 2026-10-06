<?php

namespace Tests\Unit\Vetements;

use App\Modules\Vetements\Models\CycleVieEvent;
use App\Modules\Vetements\Models\Vetement;
use Tests\TestCase;

class CycleVieHistoriqueTest extends TestCase
{
    public function test_seuls_les_statuts_clotures_vont_dans_l_historique(): void
    {
        $this->assertTrue(Vetement::estStatutHistorique(Vetement::STATUS_REPARE));
        $this->assertTrue(Vetement::estStatutHistorique(Vetement::STATUS_DONNE));
        $this->assertTrue(Vetement::estStatutHistorique(Vetement::STATUS_RECYCLE));
        $this->assertFalse(Vetement::estStatutHistorique(Vetement::STATUS_EN_ATTENTE));
        $this->assertFalse(Vetement::estStatutHistorique(Vetement::STATUS_EN_REPARATION));
        $this->assertFalse(Vetement::estStatutHistorique(null));
    }

    public function test_une_piece_reparee_est_dans_l_historique(): void
    {
        $vetement = new Vetement(['status' => Vetement::STATUS_REPARE]);
        $this->assertTrue($vetement->estDansHistorique());

        $enCours = new Vetement(['status' => Vetement::STATUS_EN_REPARATION]);
        $this->assertFalse($enCours->estDansHistorique());
    }

    public function test_l_icone_depend_de_l_etape(): void
    {
        $declare = new CycleVieEvent(['step_key' => CycleVieEvent::STEP_DECLARE]);
        $termineDon = new CycleVieEvent([
            'step_key' => CycleVieEvent::STEP_TERMINE,
            'status_snapshot' => Vetement::STATUS_DONNE,
        ]);

        $this->assertSame('plus-circle', $declare->icon());
        $this->assertSame('gift', $termineDon->icon());
    }
}
