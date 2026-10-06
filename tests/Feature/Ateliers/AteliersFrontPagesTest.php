<?php

namespace Tests\Feature\Ateliers;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Ateliers\Services\AtelierService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Mockery;
use ReflectionMethod;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

/**
 * Rendu réel des pages (Blade, routes, contrôleur) avec un AtelierService dont seules
 * les lectures Mongo sont simulées : aucune requête vers la base.
 */
class AteliersFrontPagesTest extends TestCase
{
    use BlocksRemoteMongo;

    private const ID_MARSA = '652f000000000000000000a1';

    private const ID_VERT = '652f000000000000000000a2';

    private const ID_SERVICE = '652f000000000000000000b1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function atelier(string $id, string $nom, float $lat, float $lng, float $note, array $services): Atelier
    {
        $journee = [['09:00', '12:00'], ['14:00', '18:00']];
        $atelier = new Atelier([
            'nom' => $nom,
            'ville' => 'La Marsa',
            'adresse' => '45 Avenue Habib Bourguiba',
            'telephone' => '+216 71 774 210',
            'latitude' => $lat,
            'longitude' => $lng,
            'note_moyenne' => $note,
            'nb_avis' => 58,
            'statut' => Atelier::STATUT_ACTIF,
            'horaires' => array_fill_keys(['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'], $journee) + ['samedi' => [['09:00', '13:00']], 'dimanche' => []],
        ]);
        $atelier->setAttribute('_id', $id);

        $modeles = [];
        foreach ($services as $i => [$nomService, $prix]) {
            $service = new Service(['nom' => $nomService, 'prix_estime' => $prix, 'duree_estimee' => 45, 'description' => 'Desc <b>gras</b>']);
            $service->setAttribute('_id', $i === 0 ? self::ID_SERVICE : sprintf('652f0000000000000000%04d', $i));
            $service->atelier_id = $id;
            $modeles[] = $service;
        }

        return $atelier->setRelation('services', new Collection($modeles));
    }

    private function jeu(): Collection
    {
        return new Collection([
            $this->atelier(self::ID_MARSA, 'Couture <script>alert(1)</script> Plus', 36.8782, 10.3247, 4.9, [
                ['Retouche denim', 25], ['Raccourcir un ourlet', 12], ['Changer une fermeture', 18], ['Recoudre', 10],
            ]),
            $this->atelier(self::ID_VERT, "L'Atelier Vert", 36.8235, 10.2125, 4.8, [['Upcycling créatif', 65]]),
        ]);
    }

    private function mockService(?Collection $ateliers = null): AtelierService
    {
        $service = Mockery::mock(AtelierService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('fetchActifs')->andReturn($ateliers ?? $this->jeu());
        $service->shouldReceive('serviceNames')->andReturn(['Raccourcir un ourlet', 'Retouche denim']);
        $service->shouldReceive('villes')->andReturn(['La Marsa']);
        $this->app->instance(AtelierService::class, $service);

        return $service;
    }

    public function test_liste_rendue_avec_cartes_liens_rdv_et_donnees_echappees(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 10:00', Atelier::FUSEAU_HORAIRE));
        $this->mockService();

        $response = $this->get('/ateliers');

        $response->assertOk()
            ->assertSee('Ateliers près de', false)
            ->assertSee(route('front.rdv.create', ['atelier_id' => self::ID_MARSA]), false)
            ->assertSee(route('front.ateliers.show', ['id' => self::ID_VERT]), false)
            ->assertSee('Prendre RDV chez L&#039;Atelier Vert', false)
            ->assertSee('à partir de <strong>10 TND</strong>', false)
            ->assertSee('Ouvert maintenant')
            ->assertSee('<span class="sr-only"> sur 5</span>', false)
            ->assertSee('+1')
            ->assertSee('data-map-markers', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('meilleur match', false)
            ->assertDontSee('compatibilité', false);

        $this->assertStringContainsString('/rendez-vous/create?atelier_id='.self::ID_MARSA, $response->getContent());
    }

    public function test_marqueurs_json_inline_proteges_contre_l_injection_de_script(): void
    {
        $this->mockService();

        $html = $this->get('/ateliers')->getContent();
        preg_match('#<script type="application/json" data-map-markers>(.*?)</script>#s', $html, $m);

        $this->assertNotEmpty($m);
        $this->assertStringNotContainsString('<script>', $m[1]);
        $markers = json_decode($m[1], true);
        $this->assertCount(2, $markers);
        $this->assertSame('Couture <script>alert(1)</script> Plus', $markers[0]['nom']);
        $this->assertStringContainsString('/ateliers/'.self::ID_MARSA, $markers[0]['url']);
    }

    public function test_badge_ferme_hors_horaires_heure_de_tunis(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-04 11:00', Atelier::FUSEAU_HORAIRE));
        $this->mockService();

        $this->get('/ateliers')->assertOk()->assertSee('Fermé')->assertDontSee('Ouvert maintenant');
    }

    public function test_etat_vide(): void
    {
        $this->mockService(new Collection());

        $this->get('/ateliers?q=introuvable')
            ->assertOk()
            ->assertSee('Aucun atelier trouvé')
            ->assertSee('Réinitialiser les filtres')
            ->assertSee('Retirer le filtre « introuvable »', false);
    }

    public function test_filtres_actifs_position_et_pagination_personnalisee(): void
    {
        $nombreux = new Collection();
        for ($i = 1; $i <= 10; $i++) {
            $nombreux->push($this->atelier(sprintf('652f00000000000000001%03d', $i), "Atelier {$i}", 36.8 + $i / 100, 10.2, 4.0, [['Ourlet', 10]]));
        }
        $this->mockService($nombreux);

        $response = $this->get('/ateliers?lat=36.8065&lng=10.1815&tri=distance&rayon_km=50');

        $response->assertOk()
            ->assertSee('Autour de moi')
            ->assertSee('Rayon : 50 km')
            ->assertSee('tc-pagination', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Position utilisée')
            ->assertSee(' km', false);
        $this->assertStringContainsString('page=2', $response->getContent());
        $this->assertStringContainsString('lat=36.8065', $response->getContent());
    }

    public function test_parametres_invalides_redirigent_vers_la_liste_sans_boucle(): void
    {
        $this->mockService();

        $this->get('/ateliers?note_min=9&tri=hasard')
            ->assertRedirect(route('front.ateliers'))
            ->assertSessionHasErrors(['note_min', 'tri']);
    }

    public function test_carte_json_avec_les_memes_filtres(): void
    {
        $this->mockService();

        $this->getJson('/ateliers/carte.json?lat=36.8065&lng=10.1815&rayon_km=5')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', self::ID_VERT)
            ->assertJsonStructure(['data' => [['id', 'nom', 'ville', 'latitude', 'longitude', 'note', 'specialite', 'distance_km', 'distance', 'url']]]);
    }

    public function test_carte_json_parametres_invalides_422(): void
    {
        $this->mockService();

        $this->getJson('/ateliers/carte.json?lat=200&lng=10')->assertStatus(422);
    }

    public function test_fiche_atelier_actif(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 15:00', Atelier::FUSEAU_HORAIRE));
        $service = $this->mockService();
        $service->shouldReceive('findActifOrFail')->with(self::ID_VERT)->andReturn($this->jeu()[1]);

        $response = $this->get('/ateliers/'.self::ID_VERT.'?q=denim&page=2&evil=<x>');

        $response->assertOk()
            ->assertSee('<h1>L&#039;Atelier Vert</h1>', false)
            ->assertSee('href="tel:+21671774210"', false)
            ->assertSee(e(route('front.rdv.create', ['atelier_id' => self::ID_VERT, 'service_id' => self::ID_SERVICE])), false)
            ->assertSee('Prendre RDV pour « Upcycling créatif » chez L&#039;Atelier Vert', false)
            ->assertSee('65 TND')
            ->assertSee('45 min')
            ->assertSee('Desc &lt;b&gt;gras&lt;/b&gt;', false)
            ->assertSee("Aujourd'hui", false)
            ->assertSee('class="is-today"', false)
            ->assertSee("aria-label=\"Fil d'Ariane\"", false)
            ->assertSee(e(route('front.ateliers', ['q' => 'denim', 'page' => '2'])), false)
            ->assertDontSee('evil');
    }

    public function test_fiche_introuvable_ou_non_active_404(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('findActifOrFail')->andThrow(new ModelNotFoundException());

        $this->get('/ateliers/652f0000000000000000ffff')->assertNotFound();
        $this->get('/ateliers/pas-un-id')->assertNotFound();
    }

    public function test_requete_de_fiche_limitee_aux_ateliers_actifs_avec_services_precharges(): void
    {
        $builder = app(AtelierService::class)->ficheQuery(self::ID_VERT);
        $compile = new ReflectionMethod($base = $builder->getQuery(), 'compileWheres');
        $compile->setAccessible(true);
        $mql = json_encode($compile->invoke($base));

        $this->assertStringContainsString('"statut":"actif"', $mql);
        $this->assertStringContainsString(self::ID_VERT, $mql);
        $this->assertArrayHasKey('services', $builder->getEagerLoads());
    }

    public function test_liste_et_marqueurs_partagent_une_seule_lecture_de_la_base(): void
    {
        $service = Mockery::mock(AtelierService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('fetchActifs')->once()->andReturn($this->jeu());

        $service->search(['q' => 'denim', 'lat' => '36.8', 'lng' => '10.2']);
        $service->markers(['q' => 'denim', 'lat' => '36.8', 'lng' => '10.2', 'tri' => 'distance']);
    }

    public function test_accueil_garde_son_decor_d_origine(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('class="map-preview"', false)
            ->assertSee('Solidarité Mode')
            ->assertDontSee('leaflet', false)
            ->assertDontSee('ateliers-map.js', false);
    }
}
