<?php

namespace Tests\Unit\Vetements;

use App\Modules\Vetements\Models\Vetement;
use App\Modules\Vetements\Services\PolitiqueDeuxiemeChance;
use App\Modules\Vetements\Services\ResultatAnalyseVetement;
use Tests\TestCase;

class PolitiqueDeuxiemeChanceTest extends TestCase
{
    public function test_un_defaut_localise_ouvre_une_deuxieme_chance(): void
    {
        $resultat = $this->analyser([
            'est_vetement' => true,
            'type' => 'jean bleu',
            'matiere' => 'denim',
            'etat' => 'À réparer',
            'defauts' => ['Ourlet décousu sur 4 cm'],
            'gravite' => 'legere',
            'reparable' => true,
            'confiance' => 86,
        ]);

        $this->assertTrue($resultat['deuxieme_chance']);
        $this->assertSame(PolitiqueDeuxiemeChance::VERDICT_REPARABLE, $resultat['verdict']);
        $this->assertSame(Vetement::ACTION_REPARATION, $resultat['recommandation']);
        $this->assertSame('Jean', $resultat['type']);
        $this->assertSame('Denim', $resultat['matiere']);
        $this->assertSame('Deuxième chance', $resultat['verdict_libelle']);
        $this->assertStringContainsString('Deuxième chance : oui', $resultat['resume']);
    }

    public function test_une_piece_en_bon_etat_est_orientee_vers_le_don(): void
    {
        $resultat = $this->analyser([
            'est_vetement' => true,
            'type' => 'Chemise',
            'matiere' => 'Coton',
            'etat' => 'Bon état',
            'defauts' => [],
            'gravite' => 'aucune',
            'reparable' => false,
            'confiance' => 90,
        ]);

        $this->assertFalse($resultat['deuxieme_chance']);
        $this->assertSame(PolitiqueDeuxiemeChance::VERDICT_INUTILE, $resultat['verdict']);
        $this->assertSame(Vetement::ACTION_DON, $resultat['recommandation']);
    }

    public function test_une_gravite_severe_ferme_la_reparation_meme_si_le_modele_est_optimiste(): void
    {
        $resultat = $this->analyser([
            'est_vetement' => true,
            'type' => 'Pull',
            'matiere' => 'Laine',
            'etat' => 'Abîmé',
            'defauts' => ['Trou large au niveau du coude', 'Moisissure'],
            'gravite' => 'sévère',
            'reparable' => true,
            'confiance' => 77,
        ]);

        $this->assertFalse($resultat['deuxieme_chance']);
        $this->assertSame(PolitiqueDeuxiemeChance::VERDICT_IRREPARABLE, $resultat['verdict']);
        $this->assertSame('recyclage', $resultat['recommandation']);
        $this->assertSame('severe', $resultat['gravite']);
    }

    public function test_une_photo_qui_nest_pas_un_vetement_ne_donne_pas_de_verdict(): void
    {
        $resultat = $this->analyser([
            'est_vetement' => false,
            'type' => 'Veste',
            'etat' => 'À réparer',
            'gravite' => 'legere',
            'reparable' => true,
            'confiance' => 95,
        ]);

        $this->assertNull($resultat['deuxieme_chance']);
        $this->assertSame(PolitiqueDeuxiemeChance::VERDICT_HORS_SUJET, $resultat['verdict']);
        $this->assertNull($resultat['recommandation']);
        $this->assertNull($resultat['type']);
        $this->assertSame([], $resultat['defauts']);
    }

    public function test_une_confiance_faible_est_signalee_dans_le_resume(): void
    {
        $resultat = $this->analyser([
            'est_vetement' => true,
            'type' => 'Robe',
            'etat' => 'À réparer',
            'defauts' => ['Bretelle décousue'],
            'gravite' => 'moyenne',
            'reparable' => true,
            'confiance' => 30,
        ]);

        $this->assertTrue($resultat['deuxieme_chance']);
        $this->assertStringContainsString('Confiance limitée', $resultat['resume']);
    }

    /**
     * @param  array<string, mixed>  $brut
     * @return array<string, mixed>
     */
    private function analyser(array $brut): array
    {
        return ResultatAnalyseVetement::depuisModele($brut, 'gemini-2.5-flash')->toArray();
    }
}
