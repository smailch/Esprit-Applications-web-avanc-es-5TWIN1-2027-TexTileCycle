<?php

namespace Tests\Feature\Ateliers;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Mockery\MockInterface;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

/**
 * Back office des ateliers : accès, validation et rendu. Toutes les écritures et lectures
 * Mongo passent par un AtelierService simulé ; le DSN pointe vers un port fermé.
 */
class AteliersBackOfficeTest extends TestCase
{
    use BlocksRemoteMongo;

    private const ID_ATELIER = '652f000000000000000000a1';

    private const ID_PROPRIO = '652f000000000000000000c1';

    private const ID_LIBRE = '652f000000000000000000c2';

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
    }

    private function user(string $role, string $id = '652f00000000000000000099'): User
    {
        $user = new User(['name' => ucfirst($role).' Test', 'email' => $role.'@example.test', 'role' => $role]);
        $user->setAttribute('_id', $id);

        return $user;
    }

    private function atelier(string $statut = Atelier::STATUT_ACTIF): Atelier
    {
        $atelier = new Atelier([
            'user_id' => self::ID_PROPRIO,
            'nom' => 'Couture <script>alert(1)</script> Plus',
            'specialite' => 'Denim & retouches',
            'adresse' => '45 Avenue Habib Bourguiba',
            'ville' => 'La Marsa',
            'telephone' => '+216 71 774 210',
            'latitude' => 36.8782,
            'longitude' => 10.3247,
            'note_moyenne' => 4.9,
            'nb_avis' => 58,
            'statut' => $statut,
            'horaires' => ['lundi' => [['09:00', '12:00']], 'mardi' => [], 'mercredi' => [], 'jeudi' => [], 'vendredi' => [], 'samedi' => [], 'dimanche' => []],
        ]);
        $atelier->setAttribute('_id', self::ID_ATELIER);
        $atelier->exists = true;

        $service = new Service(['nom' => 'Retouche <i>denim</i>', 'prix_estime' => 25, 'duree_estimee' => 45]);
        $service->setAttribute('_id', '652f000000000000000000b1');

        $atelier->setRelation('services', new Collection([$service]));
        $atelier->setRelation('user', $this->user(User::ROLE_ATELIER, self::ID_PROPRIO));

        return $atelier;
    }

    /**
     * @return AtelierService&MockInterface
     */
    private function mockService(): AtelierService
    {
        $service = Mockery::mock(AtelierService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $this->app->instance(AtelierService::class, $service);

        return $service;
    }

    /** Charge utile au format de l'éditeur d'horaires. */
    private function payload(array $override = []): array
    {
        $horaires = [];
        foreach (Atelier::JOURS as $jour) {
            $horaires[$jour] = ['ferme' => '0', 'plages' => [['debut' => '09:00', 'fin' => '12:00'], ['debut' => '14:00', 'fin' => '18:00']]];
        }
        $horaires['dimanche'] = ['ferme' => '1', 'plages' => [['debut' => '10:00', 'fin' => '11:00']]];
        $horaires['samedi']['plages'][] = ['debut' => '', 'fin' => ''];

        return array_replace([
            'user_id' => self::ID_LIBRE,
            'nom' => 'Atelier Neuf',
            'specialite' => 'Retouches',
            'description' => 'Description',
            'adresse' => '12 rue de Marseille',
            'ville' => 'Tunis',
            'telephone' => '+216 71 000 000',
            'latitude' => '36.8065',
            'longitude' => '10.1815',
            'statut' => Atelier::STATUT_EN_ATTENTE,
            'horaires' => $horaires,
        ], $override);
    }

    /* ---------------------------------------------------------------- Accès */

    public function test_un_citoyen_est_renvoye_vers_l_accueil_sur_toutes_les_routes_admin(): void
    {
        $service = $this->mockService();
        $service->shouldNotReceive('listForAdmin', 'create', 'update', 'delete', 'changerStatut');

        $this->actingAs($this->user(User::ROLE_CITOYEN));

        $id = self::ID_ATELIER;
        $requetes = [
            ['get', '/admin/ateliers'],
            ['get', '/admin/ateliers/creer'],
            ['post', '/admin/ateliers'],
            ['get', "/admin/ateliers/{$id}/modifier"],
            ['put', "/admin/ateliers/{$id}"],
            ['delete', "/admin/ateliers/{$id}"],
            ['patch', "/admin/ateliers/{$id}/statut"],
        ];

        foreach ($requetes as [$methode, $url]) {
            $this->{$methode}($url, $methode === 'get' ? [] : $this->payload())
                ->assertRedirect(route('front.home'));
        }
    }

    public function test_un_atelier_n_accede_pas_a_la_gestion_admin(): void
    {
        $service = $this->mockService();
        $service->shouldNotReceive('listForAdmin', 'create', 'update', 'delete', 'changerStatut', 'findOrFail');

        $this->actingAs($this->user(User::ROLE_ATELIER, self::ID_PROPRIO));

        $id = self::ID_ATELIER;
        $requetes = [
            ['get', '/admin/ateliers/creer'],
            ['post', '/admin/ateliers'],
            ['get', "/admin/ateliers/{$id}/modifier"],
            ['put', "/admin/ateliers/{$id}"],
            ['delete', "/admin/ateliers/{$id}"],
            ['patch', "/admin/ateliers/{$id}/statut"],
        ];

        foreach ($requetes as [$methode, $url]) {
            $this->{$methode}($url, $methode === 'get' ? [] : $this->payload(['statut' => Atelier::STATUT_ACTIF]))
                ->assertForbidden();
        }
    }

    public function test_la_liste_admin_redirige_un_atelier_vers_son_espace(): void
    {
        $this->mockService()->shouldNotReceive('listForAdmin', 'statsAdmin');

        $this->actingAs($this->user(User::ROLE_ATELIER))
            ->get('/admin/ateliers')
            ->assertRedirect(route('back.ateliers.profil'));
    }

    public function test_l_identifiant_doit_etre_un_object_id(): void
    {
        $this->mockService()->shouldNotReceive('findOrFail');

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->get('/admin/ateliers/pas-un-id/modifier')
            ->assertNotFound();
    }

    /* ---------------------------------------------------------- Validation */

    public function test_creation_refuse_un_statut_invalide_et_des_horaires_mal_formes(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('verifierCompteAtelier')->andReturnNull();
        $service->shouldNotReceive('create');

        $payload = $this->payload(['statut' => 'publie']);
        $payload['horaires']['lundi']['plages'] = [['debut' => '18:00', 'fin' => '09:00']];
        $payload['horaires']['funday'] = [['09:00', '10:00']];

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->from('/admin/ateliers/creer')
            ->post('/admin/ateliers', $payload)
            ->assertRedirect('/admin/ateliers/creer')
            ->assertSessionHasErrors(['statut', 'horaires']);
    }

    public function test_creation_refuse_des_horaires_au_format_heure_invalide(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('verifierCompteAtelier')->andReturnNull();
        $service->shouldNotReceive('create');

        $payload = $this->payload();
        $payload['horaires']['mardi']['plages'] = [['debut' => '9h', 'fin' => '25:00']];
        unset($payload['horaires']['mercredi']);

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->post('/admin/ateliers', $payload)
            ->assertSessionHasErrors('horaires')
            ->assertSessionDoesntHaveErrors(['statut', 'user_id']);
    }

    public function test_creation_refuse_un_compte_deja_rattache_a_un_atelier(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('verifierCompteAtelier')
            ->with(self::ID_PROPRIO, null)
            ->andReturn(AtelierService::COMPTE_DEJA_RATTACHE);
        $service->shouldNotReceive('create');

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->post('/admin/ateliers', $this->payload(['user_id' => self::ID_PROPRIO]))
            ->assertSessionHasErrors(['user_id' => 'Ce compte est déjà rattaché à un autre atelier.']);
    }

    public function test_creation_refuse_un_compte_qui_n_est_pas_un_atelier(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('verifierCompteAtelier')->andReturn(AtelierService::COMPTE_INTROUVABLE);
        $service->shouldNotReceive('create');

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->post('/admin/ateliers', $this->payload())
            ->assertSessionHasErrors('user_id');
    }

    public function test_creation_valide_convertit_les_horaires_du_formulaire(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('verifierCompteAtelier')->andReturnNull();
        $service->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data) {
                return $data['horaires']['lundi'] === [['09:00', '12:00'], ['14:00', '18:00']]
                    && $data['horaires']['samedi'] === [['09:00', '12:00'], ['14:00', '18:00']]
                    && $data['horaires']['dimanche'] === []
                    && $data['statut'] === Atelier::STATUT_EN_ATTENTE
                    && $data['user_id'] === self::ID_LIBRE;
            })
            ->andReturnUsing(fn (array $data) => new Atelier($data));

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->post('/admin/ateliers', $this->payload())
            ->assertRedirect(route('back.ateliers'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', "L'atelier « Atelier Neuf » a été créé avec le statut « En attente ».");
    }

    public function test_modification_ignore_l_atelier_courant_pour_le_controle_du_compte(): void
    {
        $atelier = $this->atelier();
        $service = $this->mockService();
        $service->shouldReceive('findOrFail')->with(self::ID_ATELIER)->andReturn($atelier);
        $service->shouldReceive('verifierCompteAtelier')->once()->with(self::ID_PROPRIO, self::ID_ATELIER)->andReturnNull();
        $service->shouldReceive('update')->once()->andReturn($atelier);

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->put('/admin/ateliers/'.self::ID_ATELIER, $this->payload(['user_id' => self::ID_PROPRIO]))
            ->assertRedirect(route('back.ateliers'))
            ->assertSessionHas('success');
    }

    public function test_changement_de_statut_refuse_une_valeur_inconnue(): void
    {
        $service = $this->mockService();
        $service->shouldNotReceive('changerStatut');

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->patch('/admin/ateliers/'.self::ID_ATELIER.'/statut', ['statut' => 'supprime'])
            ->assertSessionHasErrors('statut');
    }

    public function test_changement_de_statut_valide(): void
    {
        $suspendu = $this->atelier(Atelier::STATUT_SUSPENDU);
        $service = $this->mockService();
        $service->shouldReceive('findOrFail')->andReturn($this->atelier());
        $service->shouldReceive('nbRendezVousEnCours')->once()->with(self::ID_ATELIER)->andReturn(0);
        $service->shouldNotReceive('annulerRendezVousEnCours');
        $service->shouldReceive('changerStatut')->once()->with(self::ID_ATELIER, Atelier::STATUT_SUSPENDU)->andReturn($suspendu);

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->from('/admin/ateliers?page=2')
            ->patch('/admin/ateliers/'.self::ID_ATELIER.'/statut', ['statut' => Atelier::STATUT_SUSPENDU])
            ->assertRedirect('/admin/ateliers?page=2')
            ->assertSessionHas('success', '« Couture <script>alert(1)</script> Plus » est suspendu : il n\'apparaît plus sur le site.');
    }

    public function test_suspendre_un_atelier_avec_des_rendez_vous_demande_confirmation(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('findOrFail')->andReturn($this->atelier());
        $service->shouldReceive('nbRendezVousEnCours')->once()->with(self::ID_ATELIER)->andReturn(3);
        $service->shouldNotReceive('annulerRendezVousEnCours');
        $service->shouldNotReceive('changerStatut');

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->from('/admin/ateliers')
            ->patch('/admin/ateliers/'.self::ID_ATELIER.'/statut', ['statut' => Atelier::STATUT_SUSPENDU])
            ->assertRedirect('/admin/ateliers')
            ->assertSessionMissing('success')
            ->assertSessionHas('confirmation_statut', [
                'id' => self::ID_ATELIER,
                'nom' => 'Couture <script>alert(1)</script> Plus',
                'statut' => Atelier::STATUT_SUSPENDU,
                'action' => 'Suspendre',
                'nb' => 3,
            ]);
    }

    public function test_suspendre_apres_confirmation_annule_les_rendez_vous_puis_suspend(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('findOrFail')->andReturn($this->atelier());
        $service->shouldReceive('nbRendezVousEnCours')->once()->andReturn(3);
        $service->shouldReceive('annulerRendezVousEnCours')->once()->with(self::ID_ATELIER)->andReturn(3)->globally()->ordered();
        $service->shouldReceive('changerStatut')->once()->with(self::ID_ATELIER, Atelier::STATUT_SUSPENDU)
            ->andReturn($this->atelier(Atelier::STATUT_SUSPENDU))->globally()->ordered();

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->from('/admin/ateliers')
            ->patch('/admin/ateliers/'.self::ID_ATELIER.'/statut', ['statut' => Atelier::STATUT_SUSPENDU, 'annuler_rdv' => '1'])
            ->assertRedirect('/admin/ateliers')
            ->assertSessionHas('success', '« Couture <script>alert(1)</script> Plus » est suspendu : il n\'apparaît plus sur le site. '
                .'3 rendez-vous en cours annulé(s) : les pièces sont rendues à leurs propriétaires.');
    }

    public function test_reactiver_un_atelier_ne_touche_pas_aux_rendez_vous(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('findOrFail')->andReturn($this->atelier(Atelier::STATUT_SUSPENDU));
        $service->shouldNotReceive('nbRendezVousEnCours', 'annulerRendezVousEnCours');
        $service->shouldReceive('changerStatut')->once()->with(self::ID_ATELIER, Atelier::STATUT_ACTIF)->andReturn($this->atelier());

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->patch('/admin/ateliers/'.self::ID_ATELIER.'/statut', ['statut' => Atelier::STATUT_ACTIF])
            ->assertSessionHas('success');
    }

    public function test_la_confirmation_de_suspension_est_affichee_et_echappee(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('listForAdmin')->andReturn(new LengthAwarePaginator(new Collection(), 0, 15));
        $service->shouldReceive('statsAdmin')->andReturn(['total' => 0, Atelier::STATUT_ACTIF => 0, Atelier::STATUT_EN_ATTENTE => 0, Atelier::STATUT_SUSPENDU => 0]);

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->withSession(['confirmation_statut' => [
                'id' => self::ID_ATELIER,
                'nom' => 'Couture <script>alert(1)</script> Plus',
                'statut' => Atelier::STATUT_SUSPENDU,
                'action' => 'Suspendre',
                'nb' => 3,
            ]])
            ->get('/admin/ateliers')
            ->assertOk()
            ->assertSee('data-confirmation-statut', false)
            ->assertSee('a 3 rendez-vous en cours')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('name="annuler_rdv" value="1"', false)
            ->assertSee(route('back.ateliers.statut', ['id' => self::ID_ATELIER]), false)
            ->assertSee('Suspendre et annuler les rendez-vous');
    }

    public function test_suppression_annonce_les_services_supprimes(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('findOrFail')->andReturn($this->atelier());
        $service->shouldReceive('nbRendezVousEnCours')->once()->with(self::ID_ATELIER)->andReturn(0);
        $service->shouldReceive('delete')->once()->with(self::ID_ATELIER);

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->delete('/admin/ateliers/'.self::ID_ATELIER)
            ->assertRedirect(route('back.ateliers'))
            ->assertSessionHas('success', 'L\'atelier « Couture <script>alert(1)</script> Plus » ainsi que son service a été supprimé.');
    }

    public function test_suppression_refusee_si_l_atelier_a_des_rendez_vous_en_cours(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('findOrFail')->andReturn($this->atelier());
        $service->shouldReceive('nbRendezVousEnCours')->once()->with(self::ID_ATELIER)->andReturn(2);
        $service->shouldNotReceive('delete');

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->from('/admin/ateliers')
            ->delete('/admin/ateliers/'.self::ID_ATELIER)
            ->assertRedirect('/admin/ateliers')
            ->assertSessionMissing('success')
            ->assertSessionHas('error', '« Couture <script>alert(1)</script> Plus » a 2 rendez-vous en cours : suppression impossible. '
                .'Suspendez-le d\'abord : ses rendez-vous seront annulés et les pièces rendues à leurs propriétaires.');
    }

    /* --------------------------------------------------------------- Rendu */

    public function test_la_liste_admin_s_affiche_avec_statistiques_actions_et_echappement(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('listForAdmin')
            ->with(['q' => 'marsa'], 15)
            ->andReturn(new LengthAwarePaginator(new Collection([$this->atelier()]), 1, 15, 1, ['path' => '/admin/ateliers']));
        $service->shouldReceive('statsAdmin')->andReturn(['total' => 3, Atelier::STATUT_ACTIF => 1, Atelier::STATUT_EN_ATTENTE => 1, Atelier::STATUT_SUSPENDU => 1]);

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->get('/admin/ateliers?q=marsa&statut=inconnu')
            ->assertOk()
            ->assertSee('css/ateliers-back.css', false)
            ->assertSee('Couture &lt;script&gt;alert(1)&lt;/script&gt; Plus', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Suspendre Couture &lt;script&gt;', false)
            ->assertSee(e(route('back.ateliers.edit', ['id' => self::ID_ATELIER])), false)
            ->assertSee(e(route('front.ateliers.show', ['id' => self::ID_ATELIER])), false)
            ->assertSee('data-confirm-delete', false)
            ->assertSee('Effacer les filtres');
    }

    public function test_la_liste_vide_affiche_un_etat_dedie(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('listForAdmin')->andReturn(new LengthAwarePaginator(new Collection(), 0, 15));
        $service->shouldReceive('statsAdmin')->andReturn(['total' => 0, Atelier::STATUT_ACTIF => 0, Atelier::STATUT_EN_ATTENTE => 0, Atelier::STATUT_SUSPENDU => 0]);

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->get('/admin/ateliers')
            ->assertOk()
            ->assertSee('Aucun atelier pour le moment');
    }

    public function test_le_formulaire_de_creation_propose_les_comptes_libres_et_les_horaires_par_defaut(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('comptesAtelierDisponibles')
            ->andReturn(collect([$this->user(User::ROLE_ATELIER, self::ID_LIBRE)]));

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->get('/admin/ateliers/creer')
            ->assertOk()
            ->assertSee('value="'.self::ID_LIBRE.'"', false)
            ->assertSee('name="horaires[lundi][plages][1][fin]" value="18:00"', false)
            ->assertSee('id="ferme-dimanche" name="horaires[dimanche][ferme]" value="1" checked', false)
            ->assertSee('leaflet@1.9.4', false)
            ->assertSee('data-slot-template', false);
    }

    public function test_le_formulaire_d_edition_affiche_le_compte_et_les_services_en_lecture_seule(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('findOrFail')->with(self::ID_ATELIER)->andReturn($this->atelier());
        $service->shouldNotReceive('comptesAtelierDisponibles');

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->get('/admin/ateliers/'.self::ID_ATELIER.'/modifier')
            ->assertOk()
            ->assertSee('name="user_id" value="'.self::ID_PROPRIO.'"', false)
            ->assertSee('atelier@example.test')
            ->assertSee('Retouche &lt;i&gt;denim&lt;/i&gt;', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('value="Couture &lt;script&gt;alert(1)&lt;/script&gt; Plus"', false)
            ->assertSee('name="horaires[lundi][plages][0][debut]" value="09:00"', false);
    }

    /* ------------------------------------------------- Contrôles de saisie (PHP) */

    public function test_le_formulaire_atelier_n_a_aucun_controle_de_saisie_html(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('comptesAtelierDisponibles')
            ->andReturn(collect([$this->user(User::ROLE_ATELIER, self::ID_LIBRE)]));

        $html = $this->actingAs($this->user(User::ROLE_ADMIN))
            ->get('/admin/ateliers/creer')
            ->assertOk()
            ->getContent();

        $this->assertSame(1, preg_match('/data-atelier-form>(.*?)<\/form>/s', $html, $formulaire));
        $this->assertDoesNotMatchRegularExpression('/\s(required|maxlength|minlength|pattern|min|max|step)(=|\s|>)/', $formulaire[1]);
        $this->assertDoesNotMatchRegularExpression('/type="(number|time|email|url|date)"/', $formulaire[1]);
        $this->assertStringContainsString('aria-required="true"', $formulaire[1]);
    }

    public function test_creation_accepte_une_virgule_decimale_et_des_heures_sans_zero(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('verifierCompteAtelier')->andReturnNull();
        $service->shouldReceive('create')
            ->once()
            ->withArgs(fn (array $data) => $data['latitude'] === '36.8065'
                && $data['longitude'] === '10.1815'
                && $data['horaires']['lundi'] === [['09:00', '12:30']])
            ->andReturnUsing(fn (array $data) => new Atelier($data));

        $payload = $this->payload(['latitude' => '36,8065', 'longitude' => '10,1815']);
        $payload['horaires']['lundi']['plages'] = [['debut' => '9:00', 'fin' => '12h30']];

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->post('/admin/ateliers', $payload)
            ->assertRedirect(route('back.ateliers'))
            ->assertSessionHasNoErrors();
    }

    public function test_creation_refuse_une_position_qui_n_est_pas_un_nombre(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('verifierCompteAtelier')->andReturnNull();
        $service->shouldNotReceive('create');

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->post('/admin/ateliers', $this->payload(['latitude' => '36,8,1', 'longitude' => '']))
            ->assertSessionHasErrors([
                'latitude' => 'La latitude doit être un nombre.',
                'longitude' => 'La longitude est obligatoire si la latitude est renseignée.',
            ]);
    }

    public function test_la_validation_en_temps_reel_admin_ne_cree_pas_l_atelier(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('verifierCompteAtelier')->andReturnNull();
        $service->shouldNotReceive('create');
        $entetes = ['Precognition' => 'true', 'Accept' => 'application/json'];

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->post('/admin/ateliers', $this->payload(), $entetes)
            ->assertNoContent();

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->post('/admin/ateliers', $this->payload(['latitude' => '91', 'longitude' => '']), $entetes + ['Precognition-Validate-Only' => 'latitude,longitude'])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'latitude' => 'La latitude doit être comprise entre -90 et 90.',
                'longitude' => 'La longitude est obligatoire si la latitude est renseignée.',
            ]);
    }

    public function test_la_validation_en_temps_reel_ne_contourne_pas_les_acces(): void
    {
        $this->mockService()->shouldNotReceive('create', 'verifierCompteAtelier');

        $this->actingAs($this->user(User::ROLE_ATELIER, self::ID_PROPRIO))
            ->post('/admin/ateliers', $this->payload(), ['Precognition' => 'true', 'Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_la_recherche_admin_trop_longue_est_refusee_avec_un_message(): void
    {
        $service = $this->mockService();
        $service->shouldReceive('listForAdmin')
            ->with([], 15)
            ->andReturn(new LengthAwarePaginator(new Collection(), 0, 15, 1, ['path' => '/admin/ateliers']));
        $service->shouldReceive('statsAdmin')->andReturn(['total' => 0, Atelier::STATUT_ACTIF => 0, Atelier::STATUT_EN_ATTENTE => 0, Atelier::STATUT_SUSPENDU => 0]);
        $recherche = str_repeat('a', 101);

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->get('/admin/ateliers?q='.$recherche)
            ->assertRedirect(route('back.ateliers'))
            ->assertSessionHasErrors(['q' => 'La recherche ne doit pas dépasser 100 caractères.']);

        $this->actingAs($this->user(User::ROLE_ADMIN))
            ->followingRedirects()
            ->get('/admin/ateliers?q='.$recherche)
            ->assertOk()
            ->assertSee('La recherche ne doit pas dépasser 100 caractères.')
            ->assertSee('class="search-box compact has-error"', false)
            ->assertSee('name="q" value="'.$recherche.'"', false)
            ->assertSee('aria-invalid="true"', false);
    }
}
