<?php

namespace Tests\Feature\RendezVous;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Auth\Models\User;
use App\Modules\RendezVous\Models\RendezVous;
use App\Modules\RendezVous\Services\RendezVousService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Mockery;
use Mockery\MockInterface;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\Concerns\FabriqueRendezVous;
use Tests\Concerns\IdentifiantsRendezVous;
use Tests\TestCase;

/**
 * Prise de RDV depuis un atelier : rendu réel (routes, FormRequest, Blade) avec AtelierService
 * et les accès base de RendezVousService simulés. Aucune lecture ni écriture Mongo.
 */
class RendezVousFrontTest extends TestCase implements IdentifiantsRendezVous
{
    use BlocksRemoteMongo;
    use FabriqueRendezVous;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
        $this->figerHorloge();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  list<RendezVous>  $rdvsDuJour
     * @return RendezVousService&MockInterface
     */
    private function simuler(?Atelier $atelier, array $rdvsDuJour = []): RendezVousService
    {
        $ateliers = Mockery::mock(AtelierService::class);
        $atelier
            ? $ateliers->shouldReceive('findActifOrFail')->with((string) $atelier->getKey())->andReturn($atelier)
            : $ateliers->shouldReceive('findActifOrFail')->andThrow(new ModelNotFoundException());
        $this->app->instance(AtelierService::class, $ateliers);

        $service = Mockery::mock(RendezVousService::class, [$ateliers])->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('vetementsReparables')->andReturn(new Collection([$this->vetement()]));
        $service->shouldReceive('rdvsOccupantLaJournee')->andReturn(new Collection($rdvsDuJour));
        $service->shouldReceive('trouverVetementReparable')->andReturn($this->vetement());
        $service->shouldReceive('trouverService')->andReturnUsing(
            fn (string $atelierId, string $serviceId) => $atelier?->services->first(
                fn ($s) => (string) $s->getKey() === $serviceId && $s->atelier_id === $atelierId
            )
        );
        $this->app->instance(RendezVousService::class, $service);

        return $service;
    }

    private function urlFormulaire(): string
    {
        return route('front.rdv', ['atelier' => self::ID_ATELIER]);
    }

    private function demande(array $override = []): array
    {
        return array_replace([
            'atelier' => self::ID_ATELIER,
            'service' => self::ID_SERVICE,
            'vetement' => self::ID_VETEMENT,
            'date' => '2026-10-06',
            'heure' => '10:00',
            'notes' => 'Ourlet décousu',
        ], $override);
    }

    /* ---------------------------------------------------------- Formulaire */

    public function test_le_formulaire_preselectionne_l_atelier_et_ne_propose_que_ses_services(): void
    {
        $this->simuler($this->atelier());

        $response = $this->actingAs($this->utilisateur())
            ->get(route('front.rdv', ['atelier' => self::ID_ATELIER, 'service' => self::ID_SERVICE_2]));

        $response->assertOk()
            ->assertSee('<input type="hidden" name="atelier" value="'.self::ID_ATELIER.'">', false)
            ->assertDontSee('<select name="atelier"', false)
            ->assertDontSee('name="atelier" required', false)
            ->assertSee('Demande de rendez-vous chez Couture &lt;script&gt;alert(1)&lt;/script&gt; Atelier', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('Couture Plus')
            ->assertDontSee("L'Atelier Vert")
            ->assertSee('La Marsa')
            ->assertSee('href="tel:+21671774210"', false)
            ->assertSee("Changer d'atelier", false)
            ->assertSee(e(route('front.ateliers')), false)
            ->assertSee('Ourlet &lt;b&gt;express&lt;/b&gt; — 45 min · 15 TND', false)
            ->assertSee('<option value="'.self::ID_SERVICE_2.'" selected>', false)
            ->assertDontSee('<option value="'.self::ID_SERVICE.'" selected>', false)
            ->assertSee('Veste en jean · Taille M')
            ->assertSee('min="2026-10-05"', false)
            ->assertSee('action="'.e(route('front.rdv.store')).'"', false);

        preg_match('#<select id="rdv-service".*?</select>#s', $response->getContent(), $select);
        $this->assertSame(2, substr_count($select[0], '<option value="652f'));
    }

    public function test_un_service_d_un_autre_atelier_dans_l_url_n_est_pas_preselectionne(): void
    {
        $this->simuler($this->atelier());

        $this->actingAs($this->utilisateur())
            ->get(route('front.rdv', ['atelier' => self::ID_ATELIER, 'service' => self::ID_SERVICE_ETRANGER]))
            ->assertOk()
            ->assertDontSee(self::ID_SERVICE_ETRANGER)
            ->assertDontSee(' selected>', false);
    }

    public function test_sans_atelier_le_citoyen_est_renvoye_vers_la_liste(): void
    {
        $this->simuler(null)->shouldNotReceive('vetementsReparables');

        $this->actingAs($this->utilisateur())
            ->get(route('front.rdv'))
            ->assertRedirect(route('front.ateliers'))
            ->assertSessionHas('info', "Choisissez d'abord un atelier, puis cliquez sur « Prendre RDV ».");
    }

    public function test_un_atelier_non_actif_ou_inconnu_est_refuse(): void
    {
        $this->simuler(null);

        foreach ([self::ID_AUTRE_ATELIER, 'pas-un-id'] as $id) {
            $this->actingAs($this->utilisateur())
                ->get(route('front.rdv', ['atelier' => $id]))
                ->assertRedirect(route('front.ateliers'))
                ->assertSessionHas('info', "Cet atelier n'est pas disponible à la réservation. Choisissez-en un autre.");
        }
    }

    public function test_un_atelier_sans_service_affiche_un_message_sans_formulaire(): void
    {
        $this->simuler($this->atelier(services: []));

        $this->actingAs($this->utilisateur())
            ->get($this->urlFormulaire())
            ->assertOk()
            ->assertSee("n'a pas encore publié de services réservables", false)
            ->assertDontSee('name="service"', false);
    }

    public function test_un_visiteur_doit_se_connecter_et_un_compte_atelier_ne_reserve_pas(): void
    {
        $this->simuler($this->atelier())->shouldNotReceive('creer');

        $this->get($this->urlFormulaire())->assertRedirect(route('front.login'));

        $this->actingAs($this->utilisateur(User::ROLE_ATELIER, self::ID_COMPTE_ATELIER))
            ->get($this->urlFormulaire())
            ->assertRedirect(route('front.ateliers'))
            ->assertSessionHas('info', 'La prise de rendez-vous est réservée aux comptes citoyens.');

        $this->post(route('front.rdv.store'), $this->demande())->assertForbidden();
    }

    /* ------------------------------------------------------------ Création */

    public function test_une_demande_valide_cree_le_rdv_et_redirige_vers_la_fiche(): void
    {
        $atelier = $this->atelier();
        $service = $this->simuler($atelier);
        $service->shouldReceive('enregistrer')
            ->once()
            ->with(Mockery::on(fn (RendezVous $rdv) => $rdv->user_id === self::ID_CITOYEN
                && $rdv->atelier_id === self::ID_ATELIER
                && $rdv->service_id === self::ID_SERVICE
                && $rdv->vetement_id === self::ID_VETEMENT
                && $rdv->date === '2026-10-06'
                && $rdv->heure === '10:00'
                && $rdv->duree_minutes === 45
                && $rdv->statut === RendezVous::STATUT_EN_ATTENTE));

        $this->actingAs($this->utilisateur())
            ->from($this->urlFormulaire())
            ->post(route('front.rdv.store'), $this->demande(['statut' => RendezVous::STATUT_CONFIRME, 'user_id' => '652f0000000000000000ffff']))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('front.ateliers.show', ['id' => self::ID_ATELIER]))
            ->assertSessionHas('success', "Votre demande de rendez-vous du mardi 6 oct. 2026 à 10:00 chez « ".self::NOM_PIEGE." » a été envoyée. L'atelier doit encore la confirmer.");
    }

    public function test_le_vetement_est_facultatif(): void
    {
        $service = $this->simuler($this->atelier());
        $service->shouldReceive('enregistrer')->once()->with(Mockery::on(fn (RendezVous $rdv) => $rdv->vetement_id === null));

        $this->actingAs($this->utilisateur())
            ->post(route('front.rdv.store'), $this->demande(['vetement' => '']))
            ->assertSessionHasNoErrors();
    }

    public function test_un_service_d_un_autre_atelier_est_refuse(): void
    {
        $this->simuler($this->atelier())->shouldNotReceive('enregistrer');

        $this->actingAs($this->utilisateur())
            ->from($this->urlFormulaire())
            ->post(route('front.rdv.store'), $this->demande(['service' => self::ID_SERVICE_ETRANGER]))
            ->assertRedirect($this->urlFormulaire())
            ->assertSessionHasErrors(['service' => "Ce service n'est pas proposé par cet atelier."]);
    }

    public function test_un_atelier_non_actif_est_refuse_a_l_envoi(): void
    {
        $this->simuler(null)->shouldNotReceive('enregistrer');

        $this->actingAs($this->utilisateur())
            ->post(route('front.rdv.store'), $this->demande(['atelier' => self::ID_AUTRE_ATELIER]))
            ->assertSessionHasErrors(['atelier' => "Cet atelier n'est pas disponible à la réservation."]);
    }

    public function test_un_creneau_hors_horaires_est_refuse(): void
    {
        $this->simuler($this->atelier())->shouldNotReceive('enregistrer');

        $this->actingAs($this->utilisateur())
            ->post(route('front.rdv.store'), $this->demande(['heure' => '11:30']))
            ->assertSessionHasErrors('heure');

        $this->post(route('front.rdv.store'), $this->demande(['date' => '2026-10-11']))
            ->assertSessionHasErrors(['heure' => "L'atelier est fermé le dimanche. Choisissez un autre jour."]);
    }

    public function test_un_creneau_qui_chevauche_un_autre_rdv_est_refuse(): void
    {
        $this->simuler($this->atelier(), [$this->rdv(RendezVous::STATUT_CONFIRME, '2026-10-06', '09:30', 45)])
            ->shouldNotReceive('enregistrer');

        $this->actingAs($this->utilisateur())
            ->post(route('front.rdv.store'), $this->demande(['heure' => '10:00']))
            ->assertSessionHasErrors(['heure' => "Ce créneau chevauche un autre rendez-vous de l'atelier (09:30 – 10:15). Choisissez un autre horaire."]);
    }

    public function test_une_date_passee_et_des_formats_invalides_sont_refuses(): void
    {
        $this->simuler($this->atelier())->shouldNotReceive('enregistrer');

        $this->actingAs($this->utilisateur())
            ->post(route('front.rdv.store'), $this->demande(['date' => '2026-10-01']))
            ->assertSessionHasErrors(['date' => 'Le rendez-vous doit être fixé à une date et une heure futures.']);

        $this->post(route('front.rdv.store'), $this->demande(['date' => '06/10/2026', 'heure' => '10h', 'service' => 'x']))
            ->assertSessionHasErrors(['date', 'heure', 'service']);
    }
}
