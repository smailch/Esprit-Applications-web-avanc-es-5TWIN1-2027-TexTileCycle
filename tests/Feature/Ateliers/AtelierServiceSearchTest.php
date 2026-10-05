<?php

namespace Tests\Feature\Ateliers;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Ateliers\Services\AtelierService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\Regex;
use ReflectionMethod;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

/**
 * Sans base : la requête Mongo est inspectée (MQL compilé) et la lecture des ateliers
 * (fetchActifs) est remplacée par une collection de modèles en mémoire.
 */
class AtelierServiceSearchTest extends TestCase
{
    use BlocksRemoteMongo;

    /** Centre de Tunis. */
    private const LAT = 36.8065;

    private const LNG = 10.1815;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
    }

    private function atelier(string $nom, ?float $lat, ?float $lng, float $note, int $avis = 10, ?string $specialite = null): Atelier
    {
        $atelier = new Atelier([
            'nom' => $nom,
            'ville' => 'Tunis',
            'latitude' => $lat,
            'longitude' => $lng,
            'note_moyenne' => $note,
            'nb_avis' => $avis,
            'specialite' => $specialite,
            'statut' => Atelier::STATUT_ACTIF,
        ]);
        $atelier->setAttribute('_id', substr(md5($nom), 0, 24));

        return $atelier->setRelation('services', new Collection([new Service(['nom' => 'Ourlet'])]));
    }

    /**
     * Couture Plus (La Marsa) ≈ 15 km, L'Atelier Vert ≈ 3,4 km, Le Dé à Coudre (Bardo) ≈ 4,3 km du centre.
     */
    private function jeu(): Collection
    {
        return new Collection([
            $this->atelier('Couture Plus', 36.8782, 10.3247, 4.9, 58, 'Denim & retouches'),
            $this->atelier("L'Atelier Vert", 36.8235, 10.2125, 4.8, 41),
            $this->atelier('Le Dé à Coudre', 36.8092, 10.1406, 4.1, 14),
            $this->atelier('Atelier Yasmine', 36.8625, 10.1956, 4.8, 27),
            $this->atelier('Sans coordonnées', null, null, 5.0, 3),
        ]);
    }

    private function serviceAvec(?Collection $ateliers = null, array $idsService = []): AtelierService
    {
        $service = Mockery::mock(AtelierService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('atelierIdsDontUnServiceCorrespond')->andReturn($idsService);
        $service->shouldReceive('atelierIdsProposantService')->andReturn($idsService);

        if ($ateliers !== null) {
            $service->shouldReceive('fetchActifs')->andReturn($ateliers);
        }

        return $service;
    }

    private function mql(AtelierService $service, array $filtres): array
    {
        $compile = new ReflectionMethod($builder = $service->activeSearchQuery($filtres)->getQuery(), 'compileWheres');
        $compile->setAccessible(true);

        return $compile->invoke($builder);
    }

    /**
     * Toutes les conditions posées sur $champ, où qu'elles soient dans l'arbre $and/$or.
     */
    private function conditions(array $mql, string $champ): array
    {
        $trouvees = [];

        array_walk($mql, function ($valeur, $cle) use ($champ, &$trouvees) {
            if ($cle === $champ) {
                $trouvees[] = $valeur;
            } elseif (is_array($valeur)) {
                array_push($trouvees, ...$this->conditions($valeur, $champ));
            }
        });

        return $trouvees;
    }

    private function noms(iterable $ateliers): array
    {
        return collect($ateliers)->pluck('nom')->all();
    }

    // ------------------------------------------------------------------
    // Requête Mongo
    // ------------------------------------------------------------------

    public function test_requete_limitee_aux_ateliers_actifs(): void
    {
        $this->assertSame([Atelier::STATUT_ACTIF], $this->conditions($this->mql($this->serviceAvec(), []), 'statut'));
    }

    public function test_q_regex_insensible_a_la_casse_et_echappee_sur_nom_ville_specialite_et_services(): void
    {
        $idService = '652f00000000000000000042';
        $mql = $this->mql($this->serviceAvec(null, [$idService]), ['q' => '  Denim (jean).* ']);

        foreach (['nom', 'ville', 'specialite'] as $champ) {
            $regex = $this->conditions($mql, $champ)[0]['$regex'] ?? null;

            $this->assertInstanceOf(Regex::class, $regex, "regex absente sur {$champ}");
            $this->assertSame('Denim \(jean\)\.\*', $regex->getPattern());
            $this->assertSame('i', $regex->getFlags());
        }

        $ids = $this->conditions($mql, '_id')[0]['$in'];
        $this->assertEquals([new ObjectId($idService)], $ids);
        $this->assertNotEmpty($this->conditions($mql, '$or'), 'les critères de q doivent être combinés en OU');
    }

    public function test_q_sans_service_correspondant_ne_filtre_pas_par_id(): void
    {
        $this->assertSame([], $this->conditions($this->mql($this->serviceAvec(), ['q' => 'denim']), '_id'));
    }

    public function test_q_vide_ou_espaces_ignore(): void
    {
        $mql = $this->mql($this->serviceAvec(), ['q' => '   ', 'service' => '', 'ville' => null]);

        $this->assertSame([], $this->conditions($mql, 'nom'));
        $this->assertSame([], $this->conditions($mql, '_id'));
        $this->assertSame([], $this->conditions($mql, 'ville'));
    }

    public function test_filtre_service_nom_exact_par_ids_d_ateliers(): void
    {
        $service = $this->serviceAvec();
        $service->shouldReceive('atelierIdsProposantService')->with('Retouche denim')->andReturn([]);

        $ids = $this->conditions($this->mql($service, ['service' => 'Retouche denim']), '_id');

        $this->assertSame([['$in' => []]], $ids, 'aucun atelier ne propose ce service : aucun résultat');
    }

    public function test_filtres_ville_exacte_et_note_minimale(): void
    {
        $mql = $this->mql($this->serviceAvec(), ['ville' => 'La Marsa', 'note_min' => '4.5']);

        $this->assertSame(['La Marsa'], $this->conditions($mql, 'ville'));
        $this->assertSame([['$gte' => 4.5]], $this->conditions($mql, 'note_moyenne'));
    }

    // ------------------------------------------------------------------
    // Distance, rayon, tri, pagination (en PHP)
    // ------------------------------------------------------------------

    public function test_tri_par_defaut_pertinence_note_puis_nombre_d_avis(): void
    {
        $page = $this->serviceAvec($this->jeu())->search([]);

        $this->assertSame(
            ['Sans coordonnées', 'Couture Plus', "L'Atelier Vert", 'Atelier Yasmine', 'Le Dé à Coudre'],
            $this->noms($page->items())
        );
        $this->assertNull($page->items()[0]->distance_km);
    }

    public function test_tri_par_nom_insensible_aux_accents_et_a_la_casse(): void
    {
        $page = $this->serviceAvec($this->jeu())->search(['tri' => 'nom']);

        $this->assertSame(
            ['Atelier Yasmine', 'Couture Plus', "L'Atelier Vert", 'Le Dé à Coudre', 'Sans coordonnées'],
            $this->noms($page->items())
        );
    }

    public function test_tri_distance_sans_position_retombe_sur_pertinence(): void
    {
        $this->assertSame(
            $this->noms($this->serviceAvec($this->jeu())->search([])->items()),
            $this->noms($this->serviceAvec($this->jeu())->search(['tri' => 'distance'])->items())
        );
    }

    public function test_distance_calculee_et_tri_par_distance_sans_coordonnees_en_dernier(): void
    {
        $items = $this->serviceAvec($this->jeu())
            ->search(['lat' => (string) self::LAT, 'lng' => (string) self::LNG, 'tri' => 'distance'])
            ->items();

        $this->assertSame(
            ["L'Atelier Vert", 'Le Dé à Coudre', 'Atelier Yasmine', 'Couture Plus', 'Sans coordonnées'],
            $this->noms($items)
        );
        $this->assertEqualsWithDelta(
            round(AtelierService::distanceKm(self::LAT, self::LNG, 36.8235, 10.2125), 2),
            $items[0]->distance_km,
            0.001
        );
        $this->assertEqualsWithDelta(15.0, $items[3]->distance_km, 1.0);
        $this->assertNull($items[4]->distance_km);
    }

    public function test_rayon_exclut_les_ateliers_trop_loin_et_sans_coordonnees(): void
    {
        $page = $this->serviceAvec($this->jeu())->search(['lat' => self::LAT, 'lng' => self::LNG, 'rayon_km' => 7]);

        $this->assertSame(3, $page->total());
        $this->assertNotContains('Couture Plus', $this->noms($page->items()));
        $this->assertNotContains('Sans coordonnées', $this->noms($page->items()));
        $this->assertSame("L'Atelier Vert", $page->items()[0]->nom, 'pertinence : note 4.8 et 41 avis');
    }

    public function test_rayon_ignore_sans_position(): void
    {
        $this->assertSame(5, $this->serviceAvec($this->jeu())->search(['rayon_km' => 1])->total());
    }

    public function test_pagination_en_memoire(): void
    {
        $service = $this->serviceAvec($this->jeu());

        $page1 = $service->search(['tri' => 'nom'], 2, 1);
        $page3 = $service->search(['tri' => 'nom'], 2, 3);

        $this->assertSame(5, $page1->total());
        $this->assertSame(3, $page1->lastPage());
        $this->assertSame(['Atelier Yasmine', 'Couture Plus'], $this->noms($page1->items()));
        $this->assertSame(['Sans coordonnées'], $this->noms($page3->items()));
    }

    public function test_markers_legers_sans_ateliers_non_geolocalises(): void
    {
        $markers = $this->serviceAvec($this->jeu())->markers(['lat' => self::LAT, 'lng' => self::LNG, 'tri' => 'distance']);

        $this->assertCount(4, $markers);
        $this->assertSame(
            ['id', 'nom', 'ville', 'latitude', 'longitude', 'note', 'specialite', 'distance_km', 'distance'],
            array_keys($markers[0])
        );
        $couture = collect($markers)->firstWhere('nom', 'Couture Plus');
        $this->assertSame('4,9', $couture['note']);
        $this->assertSame('Denim & retouches', $couture['specialite']);
        $this->assertSame('Ourlet', collect($markers)->firstWhere('nom', "L'Atelier Vert")['specialite']);
        $this->assertMatchesRegularExpression('/^\d+,\d km$/', $couture['distance']);
    }

    public function test_normaliser_horaires_complete_les_7_jours_dans_l_ordre(): void
    {
        $horaires = AtelierService::normaliserHoraires([
            'mardi' => [1 => ['09:00', '12:00']],
            'lundi' => [['08:00', '10:00']],
            'dimanche' => null,
        ]);

        $this->assertSame(Atelier::JOURS, array_keys($horaires));
        $this->assertSame([['09:00', '12:00']], $horaires['mardi']);
        $this->assertSame([], $horaires['dimanche']);
        $this->assertSame([], AtelierService::normaliserHoraires(null)['lundi']);
    }
}
