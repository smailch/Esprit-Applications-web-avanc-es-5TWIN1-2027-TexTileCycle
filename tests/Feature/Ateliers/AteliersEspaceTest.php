<?php

namespace Tests\Feature\Ateliers;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Ateliers\Services\ServiceCatalogueService;
use App\Modules\Auth\Models\User;
use App\Modules\RendezVous\Services\RendezVousService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use Mockery\MockInterface;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

/**
 * Espace du rôle atelier (profil + services). AtelierService et ServiceCatalogueService sont
 * simulés : aucune lecture ni écriture Mongo, le DSN pointe vers un port fermé.
 */
class AteliersEspaceTest extends TestCase
{
    use BlocksRemoteMongo;

    private const ID_USER = '652f000000000000000000c1';

    private const ID_ATELIER = '652f000000000000000000a1';

    private const ID_SERVICE = '652f000000000000000000b1';

    private const ID_SERVICE_ETRANGER = '652f000000000000000000b9';

    private const NOM_PIEGE = 'Couture <script>alert(1)</script> Plus';

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();

        $rendezVous = Mockery::mock(RendezVousService::class);
        $rendezVous->shouldReceive('resumeAtelier')->andReturn(['en_attente' => 0, 'prochains' => new Collection()]);
        $this->app->instance(RendezVousService::class, $rendezVous);
    }

    private function user(string $role = User::ROLE_ATELIER, string $id = self::ID_USER): User
    {
        $user = new User(['name' => 'Samia Ben Ali', 'email' => 'samia@example.test', 'role' => $role]);
        $user->setAttribute('_id', $id);

        return $user;
    }

    private function service(string $id = self::ID_SERVICE, string $nom = 'Ourlet <b>express</b>', float $prix = 12.5, int $duree = 30): Service
    {
        $service = new Service(['nom' => $nom, 'description' => 'Description <i>piégée</i>', 'prix_estime' => $prix, 'duree_estimee' => $duree]);
        $service->setAttribute('_id', $id);
        $service->atelier_id = self::ID_ATELIER;
        $service->exists = true;

        return $service;
    }

    private function atelier(string $statut = Atelier::STATUT_EN_ATTENTE, array $services = []): Atelier
    {
        $atelier = new Atelier([
            'nom' => self::NOM_PIEGE,
            'specialite' => 'Denim',
            'adresse' => '45 Avenue Habib Bourguiba',
            'ville' => 'La Marsa',
            'telephone' => '+216 71 774 210',
            'latitude' => 36.8782,
            'longitude' => 10.3247,
            'horaires' => ['lundi' => [['09:00', '12:00']]],
        ]);
        $atelier->setAttribute('_id', self::ID_ATELIER);
        $atelier->user_id = self::ID_USER;
        $atelier->statut = $statut;
        $atelier->note_moyenne = 4.6;
        $atelier->nb_avis = 12;
        $atelier->exists = true;

        return $atelier->setRelation('services', new Collection($services));
    }

    /**
     * @return AtelierService&MockInterface
     */
    private function mockAteliers(?Atelier $atelier): AtelierService
    {
        $mock = Mockery::mock(AtelierService::class)->makePartial();
        $mock->shouldReceive('findOwnedByUser')->with(self::ID_USER)->andReturn($atelier);
        $this->app->instance(AtelierService::class, $mock);

        return $mock;
    }

    /**
     * @return ServiceCatalogueService&MockInterface
     */
    private function mockCatalogue(): ServiceCatalogueService
    {
        $mock = Mockery::mock(ServiceCatalogueService::class);
        $this->app->instance(ServiceCatalogueService::class, $mock);

        return $mock;
    }

    private function horairesFormulaire(): array
    {
        $horaires = [];
        foreach (Atelier::JOURS as $jour) {
            $horaires[$jour] = ['ferme' => '0', 'plages' => [['debut' => '09:00', 'fin' => '18:00']]];
        }
        $horaires['dimanche'] = ['ferme' => '1'];

        return $horaires;
    }

    private function profilPayload(array $override = []): array
    {
        return array_replace([
            'nom' => 'Couture Plus',
            'specialite' => 'Retouches',
            'description' => 'Atelier de quartier',
            'adresse' => '45 Avenue Habib Bourguiba',
            'ville' => 'La Marsa',
            'telephone' => '+216 71 774 210',
            'latitude' => '36.8782',
            'longitude' => '10.3247',
            'horaires' => $this->horairesFormulaire(),
        ], $override);
    }

    private function routesEspace(): array
    {
        return [
            ['get', '/admin/ateliers/mon-profil'],
            ['put', '/admin/ateliers/mon-profil'],
            ['get', '/admin/ateliers/mes-services'],
            ['post', '/admin/ateliers/mes-services'],
            ['put', '/admin/ateliers/mes-services/'.self::ID_SERVICE],
            ['delete', '/admin/ateliers/mes-services/'.self::ID_SERVICE],
        ];
    }

    /* ---------------------------------------------------------------- Accès */

    public function test_un_citoyen_est_renvoye_vers_l_accueil(): void
    {
        $this->mockAteliers(null)->shouldNotReceive('findOwnedByUser', 'updateOwn');
        $this->mockCatalogue()->shouldNotReceive('list', 'create', 'update', 'delete', 'find');

        $this->actingAs($this->user(User::ROLE_CITOYEN));

        foreach ($this->routesEspace() as [$methode, $url]) {
            $this->{$methode}($url, ['nom' => 'x'])->assertRedirect(route('front.home'));
        }
    }

    public function test_un_admin_ne_peut_pas_utiliser_l_espace_atelier(): void
    {
        $this->mockAteliers(null)->shouldNotReceive('findOwnedByUser', 'updateOwn');
        $this->mockCatalogue()->shouldNotReceive('list', 'create', 'update', 'delete', 'find');

        $this->actingAs($this->user(User::ROLE_ADMIN, '652f000000000000000000d1'));

        foreach ($this->routesEspace() as [$methode, $url]) {
            $this->{$methode}($url, $this->profilPayload())->assertForbidden();
        }
    }

    public function test_un_atelier_sans_fiche_voit_un_etat_vide_et_ne_peut_rien_ecrire(): void
    {
        $this->mockAteliers(null)->shouldNotReceive('updateOwn');
        $this->mockCatalogue()->shouldNotReceive('list', 'create', 'update', 'delete', 'find');

        $this->actingAs($this->user());

        foreach (['/admin/ateliers/mon-profil', '/admin/ateliers/mes-services'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee("Votre atelier n'est pas encore configuré. Contactez l'administrateur.", false)
                ->assertDontSee('data-atelier-form', false);
        }

        $this->put('/admin/ateliers/mon-profil', $this->profilPayload())
            ->assertRedirect(route('back.ateliers.profil'))
            ->assertSessionHas('error');

        $this->post('/admin/ateliers/mes-services', ['nom' => 'Ourlet', 'prix_estime' => 10, 'duree_estimee' => 30])
            ->assertRedirect(route('back.ateliers.profil'));
    }

    /* -------------------------------------------------------------- Profil */

    public function test_le_profil_affiche_le_bon_atelier_echappe_avec_bandeau_et_onglets(): void
    {
        $this->mockAteliers($this->atelier(Atelier::STATUT_EN_ATTENTE, [$this->service()]));

        $this->actingAs($this->user())
            ->get('/admin/ateliers/mon-profil')
            ->assertOk()
            ->assertSee('Couture &lt;script&gt;alert(1)&lt;/script&gt; Plus', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee("Votre atelier est en cours de validation, il n'est pas encore visible sur la carte", false)
            ->assertDontSee('Voir ma fiche publique')
            ->assertSee('aria-current="page"', false)
            ->assertSee(e(route('back.ateliers.services')), false)
            ->assertSee('samia@example.test')
            ->assertDontSee('name="user_id"', false)
            ->assertDontSee('name="statut"', false)
            ->assertSee('name="horaires[lundi][plages][0][debut]" value="09:00"', false)
            ->assertSee('data-location-picker', false)
            ->assertSee('4,6');
    }

    public function test_le_profil_actif_montre_la_fiche_publique_sans_bandeau(): void
    {
        $this->mockAteliers($this->atelier(Atelier::STATUT_ACTIF));

        $this->actingAs($this->user())
            ->get('/admin/ateliers/mon-profil')
            ->assertSee('Voir ma fiche publique')
            ->assertSee(e(route('front.ateliers.show', ['id' => self::ID_ATELIER])), false)
            ->assertDontSee('en cours de validation')
            ->assertDontSee('est suspendu');
    }

    public function test_le_profil_suspendu_affiche_le_bandeau_dedie(): void
    {
        $this->mockAteliers($this->atelier(Atelier::STATUT_SUSPENDU));

        $this->actingAs($this->user())
            ->get('/admin/ateliers/mon-profil')
            ->assertSee("Votre atelier est suspendu, contactez l'administrateur.", false)
            ->assertDontSee('Voir ma fiche publique');
    }

    public function test_la_mise_a_jour_du_profil_ignore_statut_note_et_compte(): void
    {
        $ateliers = $this->mockAteliers($this->atelier());
        $ateliers->shouldReceive('updateOwn')
            ->once()
            ->withArgs(function (string $userId, array $data) {
                return $userId === self::ID_USER
                    && ! array_intersect(array_keys($data), ['statut', 'note_moyenne', 'nb_avis', 'user_id', '_id'])
                    && $data['nom'] === 'Couture Plus'
                    && $data['horaires']['lundi'] === [['09:00', '18:00']]
                    && $data['horaires']['dimanche'] === [];
            })
            ->andReturn($this->atelier());

        $this->actingAs($this->user())
            ->put('/admin/ateliers/mon-profil', $this->profilPayload([
                'statut' => Atelier::STATUT_ACTIF,
                'note_moyenne' => 5,
                'nb_avis' => 999,
                'user_id' => '652f000000000000000000ff',
                '_id' => '652f000000000000000000ee',
            ]))
            ->assertRedirect(route('back.ateliers.profil'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
    }

    public function test_la_mise_a_jour_du_profil_refuse_des_horaires_mal_formes(): void
    {
        $this->mockAteliers($this->atelier())->shouldNotReceive('updateOwn');

        $payload = $this->profilPayload();
        $payload['horaires']['mardi']['plages'] = [['debut' => '18:00', 'fin' => '08:00']];
        $payload['horaires']['jeudi']['plages'] = [['debut' => '9h', 'fin' => '12:00']];

        $this->actingAs($this->user())
            ->from('/admin/ateliers/mon-profil')
            ->put('/admin/ateliers/mon-profil', $payload)
            ->assertRedirect('/admin/ateliers/mon-profil')
            ->assertSessionHasErrors('horaires');
    }

    /* ------------------------------------------------------------ Services */

    public function test_la_liste_des_services_affiche_le_catalogue_de_l_atelier_connecte(): void
    {
        $atelier = $this->atelier(Atelier::STATUT_ACTIF);
        $this->mockAteliers($atelier);
        $this->mockCatalogue()->shouldReceive('list')
            ->once()
            ->with(Mockery::on(fn ($a) => $a === $atelier))
            ->andReturn(new Collection([
                $this->service(),
                $this->service('652f000000000000000000b2', 'Upcycling veste', 60, 90),
            ]));

        $this->actingAs($this->user())
            ->get('/admin/ateliers/mes-services')
            ->assertOk()
            ->assertSee('Ourlet &lt;b&gt;express&lt;/b&gt;', false)
            ->assertSee('aria-label="Modifier le service Ourlet &lt;b&gt;express&lt;/b&gt;"', false)
            ->assertSee('aria-label="Supprimer le service Upcycling veste"', false)
            ->assertSee('Description &lt;i&gt;piégée&lt;/i&gt;', false)
            ->assertSee('12,50 TND')
            ->assertSee('1 h')
            ->assertSee('Prix (TND)')
            ->assertSee(e(route('back.ateliers.services.update', ['id' => self::ID_SERVICE])), false)
            ->assertDontSee('data-open-on-load', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_un_catalogue_vide_affiche_l_etat_d_aide(): void
    {
        $this->mockAteliers($this->atelier());
        $this->mockCatalogue()->shouldReceive('list')->andReturn(new Collection());

        $this->actingAs($this->user())
            ->get('/admin/ateliers/mes-services')
            ->assertOk()
            ->assertSee('Ajouter mon premier service')
            ->assertDontSee('<table', false);
    }

    public function test_la_creation_utilise_l_atelier_connecte_et_ignore_atelier_id(): void
    {
        $atelier = $this->atelier();
        $this->mockAteliers($atelier);
        $this->mockCatalogue()->shouldReceive('create')
            ->once()
            ->withArgs(fn (Atelier $a, array $data) => $a === $atelier
                && ! array_key_exists('atelier_id', $data)
                && ! array_key_exists('_modal', $data)
                && $data['prix_estime'] === '15')
            ->andReturn(new Service(['nom' => 'Ourlet']));

        $this->actingAs($this->user())
            ->post('/admin/ateliers/mes-services', [
                'nom' => 'Ourlet', 'description' => '', 'prix_estime' => '15', 'duree_estimee' => '30',
                'atelier_id' => '652f000000000000000000ff', '_modal' => 'create',
            ])
            ->assertRedirect(route('back.ateliers.services'))
            ->assertSessionHas('success', 'Le service « Ourlet » a été ajouté à votre catalogue.');
    }

    public function test_la_creation_refuse_un_prix_negatif_et_une_duree_hors_plage(): void
    {
        $this->mockAteliers($this->atelier());
        $this->mockCatalogue()->shouldNotReceive('create');

        $this->actingAs($this->user())
            ->from('/admin/ateliers/mes-services')
            ->post('/admin/ateliers/mes-services', ['nom' => 'Ourlet', 'prix_estime' => '-5', 'duree_estimee' => '2', '_modal' => 'create'])
            ->assertRedirect('/admin/ateliers/mes-services')
            ->assertSessionHasErrors([
                'prix_estime' => 'Le prix estimé ne peut pas être négatif.',
                'duree_estimee' => 'La durée estimée doit être comprise entre 5 et 480 minutes.',
            ]);
    }

    public function test_la_modification_refuse_une_duree_trop_longue(): void
    {
        $this->mockAteliers($this->atelier());
        $this->mockCatalogue()->shouldNotReceive('update');

        $this->actingAs($this->user())
            ->put('/admin/ateliers/mes-services/'.self::ID_SERVICE, ['nom' => 'Ourlet', 'prix_estime' => '10', 'duree_estimee' => '600'])
            ->assertSessionHasErrors('duree_estimee');
    }

    public function test_une_erreur_rouvre_la_modale_de_creation_avec_les_anciennes_valeurs(): void
    {
        $this->mockAteliers($this->atelier());
        $this->mockCatalogue()->shouldReceive('list')->andReturn(new Collection([$this->service()]));

        $this->actingAs($this->user())
            ->withSession([
                'errors' => (new ViewErrorBag())->put('default', new MessageBag(['prix_estime' => ['Le prix estimé ne peut pas être négatif.']])),
                '_old_input' => ['_modal' => 'create', 'nom' => 'Ourlet <u>maison</u>', 'prix_estime' => '-5', 'duree_estimee' => '30'],
            ])
            ->get('/admin/ateliers/mes-services')
            ->assertOk()
            ->assertSee('data-open-on-load', false)
            ->assertSee('value="Ourlet &lt;u&gt;maison&lt;/u&gt;"', false)
            ->assertSee('value="-5"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('Le prix estimé ne peut pas être négatif.')
            ->assertSee('action="'.e(route('back.ateliers.services.store')).'"', false)
            ->assertSee('Nouveau service');
    }

    public function test_une_erreur_rouvre_la_modale_d_edition_du_bon_service(): void
    {
        $this->mockAteliers($this->atelier());
        $this->mockCatalogue()->shouldReceive('list')->andReturn(new Collection([$this->service()]));

        $this->actingAs($this->user())
            ->withSession([
                'errors' => (new ViewErrorBag())->put('default', new MessageBag(['duree_estimee' => ['La durée estimée doit être comprise entre 5 et 480 minutes.']])),
                '_old_input' => ['_modal' => 'edit', '_service' => self::ID_SERVICE, 'nom' => 'Ourlet modifié', 'prix_estime' => '12', 'duree_estimee' => '900'],
            ])
            ->get('/admin/ateliers/mes-services')
            ->assertOk()
            ->assertSee('data-open-on-load', false)
            ->assertSee('Modifier le service</h2>', false)
            ->assertSee('action="'.e(route('back.ateliers.services.update', ['id' => self::ID_SERVICE])).'"', false)
            ->assertSee('value="900"', false)
            ->assertSee('value="Ourlet modifié"', false);
    }

    public function test_une_erreur_sur_un_service_inconnu_ne_rouvre_pas_la_modale(): void
    {
        $this->mockAteliers($this->atelier());
        $this->mockCatalogue()->shouldReceive('list')->andReturn(new Collection([$this->service()]));

        $this->actingAs($this->user())
            ->withSession([
                'errors' => (new ViewErrorBag())->put('default', new MessageBag(['nom' => ['Le nom du service est obligatoire.']])),
                '_old_input' => ['_modal' => 'edit', '_service' => self::ID_SERVICE_ETRANGER],
            ])
            ->get('/admin/ateliers/mes-services')
            ->assertOk()
            ->assertDontSee('data-open-on-load', false)
            ->assertSee('Le nom du service est obligatoire.');
    }

    public function test_modifier_son_propre_service(): void
    {
        $atelier = $this->atelier();
        $this->mockAteliers($atelier);
        $catalogue = $this->mockCatalogue();
        $catalogue->shouldReceive('find')->once()->with($atelier, self::ID_SERVICE)->andReturn($this->service());
        $catalogue->shouldReceive('update')
            ->once()
            ->withArgs(fn (Atelier $a, string $id, array $data) => $a === $atelier && $id === self::ID_SERVICE && ! array_key_exists('atelier_id', $data))
            ->andReturn($this->service(self::ID_SERVICE, 'Ourlet express'));

        $this->actingAs($this->user())
            ->put('/admin/ateliers/mes-services/'.self::ID_SERVICE, [
                'nom' => 'Ourlet express', 'prix_estime' => '14', 'duree_estimee' => '40', 'atelier_id' => '652f000000000000000000ff',
            ])
            ->assertRedirect(route('back.ateliers.services'))
            ->assertSessionHas('success', 'Le service « Ourlet express » a été mis à jour.');
    }

    public function test_le_service_d_un_autre_atelier_donne_une_404(): void
    {
        $atelier = $this->atelier();
        $this->mockAteliers($atelier);
        $catalogue = $this->mockCatalogue();
        $catalogue->shouldReceive('find')
            ->with($atelier, self::ID_SERVICE_ETRANGER)
            ->andThrow((new ModelNotFoundException())->setModel(Service::class));
        $catalogue->shouldNotReceive('update', 'delete');

        $this->actingAs($this->user());

        $this->put('/admin/ateliers/mes-services/'.self::ID_SERVICE_ETRANGER, ['nom' => 'Piratage', 'prix_estime' => '1', 'duree_estimee' => '10'])
            ->assertNotFound();

        $this->delete('/admin/ateliers/mes-services/'.self::ID_SERVICE_ETRANGER)
            ->assertNotFound();
    }

    public function test_un_service_rattache_a_un_autre_atelier_est_refuse_par_la_policy(): void
    {
        $atelier = $this->atelier();
        $this->mockAteliers($atelier);
        $intrus = $this->service();
        $intrus->atelier_id = '652f000000000000000000aa';
        $catalogue = $this->mockCatalogue();
        $catalogue->shouldReceive('find')->andReturn($intrus);
        $catalogue->shouldNotReceive('delete');

        $this->actingAs($this->user())
            ->delete('/admin/ateliers/mes-services/'.self::ID_SERVICE)
            ->assertForbidden();
    }

    public function test_supprimer_son_propre_service(): void
    {
        $atelier = $this->atelier();
        $this->mockAteliers($atelier);
        $catalogue = $this->mockCatalogue();
        $catalogue->shouldReceive('find')->andReturn($this->service());
        $catalogue->shouldReceive('delete')->once()->with($atelier, self::ID_SERVICE);

        $this->actingAs($this->user())
            ->delete('/admin/ateliers/mes-services/'.self::ID_SERVICE)
            ->assertRedirect(route('back.ateliers.services'))
            ->assertSessionHas('success', 'Le service « Ourlet <b>express</b> » a été supprimé.');
    }

    public function test_un_identifiant_qui_n_est_pas_un_object_id_donne_une_404(): void
    {
        $this->mockAteliers($this->atelier());
        $this->mockCatalogue()->shouldNotReceive('find');

        $this->actingAs($this->user())
            ->delete('/admin/ateliers/mes-services/pas-un-id')
            ->assertNotFound();
    }
}
