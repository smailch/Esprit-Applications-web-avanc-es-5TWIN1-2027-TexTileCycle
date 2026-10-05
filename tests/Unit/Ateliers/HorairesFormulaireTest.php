<?php

namespace Tests\Unit\Ateliers;

use App\Modules\Ateliers\Support\HorairesFormulaire;
use PHPUnit\Framework\TestCase;

class HorairesFormulaireTest extends TestCase
{
    public function test_depuis_formulaire_convertit_les_plages_et_les_jours_fermes(): void
    {
        $resultat = HorairesFormulaire::depuisFormulaire([
            'lundi' => ['ferme' => '0', 'plages' => [['debut' => '09:00', 'fin' => '12:00'], ['debut' => '', 'fin' => '']]],
            'mardi' => ['ferme' => '1', 'plages' => [['debut' => '09:00', 'fin' => '12:00']]],
            'mercredi' => ['ferme' => '0'],
            'jeudi' => [['10:00', '11:00']],
            'funday' => 'x',
        ]);

        $this->assertSame([['09:00', '12:00']], $resultat['lundi']);
        $this->assertSame([], $resultat['mardi']);
        $this->assertSame([], $resultat['mercredi']);
        $this->assertSame([['10:00', '11:00']], $resultat['jeudi']);
        $this->assertSame('x', $resultat['funday']);
    }

    public function test_depuis_formulaire_conserve_une_plage_incomplete_pour_que_la_regle_la_signale(): void
    {
        $resultat = HorairesFormulaire::depuisFormulaire([
            'lundi' => ['ferme' => '0', 'plages' => [['debut' => '09:00', 'fin' => '']]],
        ]);

        $this->assertSame([['09:00', '']], $resultat['lundi']);
        $this->assertSame('pas un tableau', HorairesFormulaire::depuisFormulaire('pas un tableau'));
    }

    public function test_pour_formulaire_accepte_les_deux_formats_et_renvoie_sept_jours(): void
    {
        $depuisStockage = HorairesFormulaire::pourFormulaire(['lundi' => [['09:00', '12:00']], 'dimanche' => []]);

        $this->assertCount(7, $depuisStockage);
        $this->assertSame(['ferme' => false, 'plages' => [['09:00', '12:00']]], $depuisStockage['lundi']);
        $this->assertSame(['ferme' => true, 'plages' => []], $depuisStockage['dimanche']);
        $this->assertSame(['ferme' => true, 'plages' => []], $depuisStockage['mardi']);

        $depuisOld = HorairesFormulaire::pourFormulaire([
            'samedi' => ['ferme' => '1', 'plages' => [['debut' => '09:00', 'fin' => '13:00']]],
        ]);

        $this->assertSame(['ferme' => true, 'plages' => [['09:00', '13:00']]], $depuisOld['samedi']);
    }

    public function test_pour_formulaire_utilise_les_horaires_par_defaut_si_la_source_est_invalide(): void
    {
        $jours = HorairesFormulaire::pourFormulaire(null);

        $this->assertSame([['09:00', '12:00'], ['14:00', '18:00']], $jours['lundi']['plages']);
        $this->assertTrue($jours['dimanche']['ferme']);
    }
}
