<?php

namespace Tests\Unit\Ateliers;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Ateliers\Services\RecommandationService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Classement expliqué des ateliers pour une même réparation (aucune base : modèles en mémoire).
 */
class RecommandationServiceTest extends TestCase
{
    /** Un mardi à 10 h, heure de Tunis. */
    private function moment(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-10-13 10:00', Atelier::FUSEAU_HORAIRE);
    }

    private function offre(string $nom, float $note, int $avis, ?float $prix, ?array $coordonnees = null, bool $ouvert = false): array
    {
        $jour = $ouvert ? [['09:00', '18:00']] : [];
        $atelier = new Atelier([
            'nom' => $nom, 'note_moyenne' => $note, 'nb_avis' => $avis, 'statut' => Atelier::STATUT_ACTIF,
            'latitude' => $coordonnees[0] ?? null, 'longitude' => $coordonnees[1] ?? null,
            'horaires' => array_fill_keys(Atelier::JOURS, $jour),
        ]);
        $atelier->setAttribute('_id', md5($nom));

        return ['atelier' => $atelier, 'service' => new Service(['nom' => 'Recoudre une déchirure', 'prix_estime' => $prix])];
    }

    private function noms(array $classement): array
    {
        return array_map(fn (array $ligne) => $ligne['atelier']->nom, $classement);
    }

    public function test_liste_vide(): void
    {
        $this->assertSame([], (new RecommandationService())->classer([]));
    }

    public function test_la_moyenne_bayesienne_ne_favorise_pas_une_note_sur_un_seul_avis(): void
    {
        $service = new RecommandationService();
        $unAvis = $this->offre('Un seul avis', 5.0, 1, 20);
        $cinquanteAvis = $this->offre('Cinquante avis', 4.8, 50, 20);

        $this->assertLessThan($service->noteBayesienne($cinquanteAvis['atelier']), $service->noteBayesienne($unAvis['atelier']));
        $this->assertSame(['Cinquante avis', 'Un seul avis'], $this->noms($service->classer([$unAvis, $cinquanteAvis], null, $this->moment())));
    }

    public function test_sans_position_la_note_passe_avant_un_petit_ecart_de_prix(): void
    {
        $classement = (new RecommandationService())->classer([
            $this->offre('Note 3,1', 3.1, 12, 8),
            $this->offre('Note 4,9', 4.9, 12, 15),
            $this->offre('Note 4,2', 4.2, 12, 10),
        ], null, $this->moment());

        $this->assertSame(['Note 4,9', 'Note 4,2', 'Note 3,1'], $this->noms($classement));
        $this->assertNull($classement[0]['distance_km']);
        $this->assertNotContains(RecommandationService::RAISON_PROCHE, array_merge(...array_column($classement, 'raisons')));
    }

    public function test_avec_position_l_atelier_proche_passe_devant_et_les_raisons_sont_donnees(): void
    {
        $position = [36.8065, 10.1815]; // Tunis centre
        $classement = (new RecommandationService())->classer([
            $this->offre('Loin, très bien noté', 4.8, 40, 20, [36.8782, 10.3247]),   // La Marsa, ~15 km
            $this->offre('Tout près', 4.5, 40, 25, [36.8070, 10.1820]),              // quelques dizaines de mètres
        ], $position, $this->moment());

        $this->assertSame(['Tout près', 'Loin, très bien noté'], $this->noms($classement));
        $this->assertLessThan(1.0, $classement[0]['distance_km']);
        $this->assertSame([RecommandationService::RAISON_PROCHE], $classement[0]['raisons']);
        $this->assertSame([RecommandationService::RAISON_NOTE, RecommandationService::RAISON_PRIX], $classement[1]['raisons']);
    }

    public function test_une_raison_n_est_donnee_qu_en_cas_de_vrai_gagnant(): void
    {
        $classement = (new RecommandationService())->classer([
            $this->offre('A', 4.5, 10, 12),
            $this->offre('B', 4.5, 10, 12),
        ], null, $this->moment());

        $this->assertSame([[], []], array_column($classement, 'raisons'));
    }

    public function test_un_atelier_sans_avis_n_est_pas_le_mieux_note_et_seul_il_n_a_pas_de_raison(): void
    {
        $service = new RecommandationService();

        $sansAvis = $service->classer([$this->offre('Nouveau', 0.0, 0, 10), $this->offre('Noté', 3.0, 3, 30)], null, $this->moment());
        $this->assertSame([RecommandationService::RAISON_NOTE], $sansAvis[array_search('Noté', $this->noms($sansAvis), true)]['raisons']);

        $seul = $service->classer([$this->offre('Seul', 4.9, 30, 10, null, true)], null, $this->moment());
        $this->assertSame([], $seul[0]['raisons']);
        $this->assertTrue($seul[0]['ouvert']);
    }

    public function test_l_ouverture_compte_dans_le_score(): void
    {
        $classement = (new RecommandationService())->classer([
            $this->offre('Fermé', 4.5, 20, 15),
            $this->offre('Ouvert', 4.5, 20, 15, null, true),
        ], null, $this->moment());

        $this->assertSame(['Ouvert', 'Fermé'], $this->noms($classement));
    }
}
