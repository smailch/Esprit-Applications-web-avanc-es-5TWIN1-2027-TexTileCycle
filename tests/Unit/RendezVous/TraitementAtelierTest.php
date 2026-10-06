<?php

namespace Tests\Unit\RendezVous;

use App\Modules\RendezVous\Models\RendezVous;
use App\Modules\Vetements\Models\Vetement;
use Tests\TestCase;

class TraitementAtelierTest extends TestCase
{
    public function test_transitions_atelier_depuis_en_attente(): void
    {
        $rdv = new RendezVous(['statut' => RendezVous::STATUT_EN_ATTENTE]);

        $this->assertSame(
            [RendezVous::STATUT_CONFIRME, RendezVous::STATUT_ANNULE],
            $rdv->transitionsAtelier()
        );
    }

    public function test_transitions_atelier_depuis_confirme(): void
    {
        $rdv = new RendezVous(['statut' => RendezVous::STATUT_CONFIRME]);

        $this->assertSame(
            [RendezVous::STATUT_TERMINE, RendezVous::STATUT_ANNULE],
            $rdv->transitionsAtelier()
        );
        $this->assertSame([], (new RendezVous(['statut' => RendezVous::STATUT_TERMINE]))->transitionsAtelier());
    }

    public function test_un_rendez_vous_est_scope_par_atelier(): void
    {
        $rdv = new RendezVous(['atelier_id' => '652f000000000000000000a1']);

        $this->assertTrue($rdv->appartientALatelier('652f000000000000000000a1'));
        $this->assertFalse($rdv->appartientALatelier('652f000000000000000000a2'));
    }

    public function test_traitements_vetement_selon_le_statut(): void
    {
        $attente = new Vetement(['status' => Vetement::STATUS_EN_ATTENTE, 'atelier_id' => 'a1']);
        $enCours = new Vetement(['status' => Vetement::STATUS_EN_REPARATION, 'atelier_id' => 'a1']);
        $repare = new Vetement(['status' => Vetement::STATUS_REPARE, 'atelier_id' => 'a1']);

        $this->assertSame([Vetement::STATUS_EN_REPARATION], $attente->traitementsAtelier());
        $this->assertSame([Vetement::STATUS_REPARE], $enCours->traitementsAtelier());
        $this->assertSame([], $repare->traitementsAtelier());
        $this->assertTrue($attente->appartientALatelier('a1'));
        $this->assertFalse($attente->appartientALatelier('a2'));
    }
}
