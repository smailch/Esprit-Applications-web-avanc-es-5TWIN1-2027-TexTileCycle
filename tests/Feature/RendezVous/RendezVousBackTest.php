<?php

namespace Tests\Feature\RendezVous;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Auth\Http\Middleware\EnsureBackOfficeRoute;
use App\Modules\Auth\Models\User;
use App\Modules\RendezVous\Models\RendezVous;
use App\Modules\RendezVous\Services\RendezVousService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Mockery\MockInterface;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\Concerns\FabriqueRendezVous;
use Tests\Concerns\IdentifiantsRendezVous;
use Tests\TestCase;

/**
 * Back office des rendez-vous : AtelierService et RendezVousService sont simulés
 * (sauf la logique de transition, réelle avec l'enregistrement simulé).
 */
class RendezVousBackTest extends TestCase implements IdentifiantsRendezVous
{
    use BlocksRemoteMongo;
    use FabriqueRendezVous;

    private const ID_ADMIN = '652f000000000000000000d1';

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

    private function compteAtelier(): User
    {
        return $this->utilisateur(User::ROLE_ATELIER, self::ID_COMPTE_ATELIER, 'Samia Atelier');
    }

    private function admin(): User
    {
        return $this->utilisateur(User::ROLE_ADMIN, self::ID_ADMIN, 'Admin');
    }

    /**
     * @return AtelierService&MockInterface
     */
    private function mockAteliers(?Atelier $atelier): AtelierService
    {
        $mock = Mockery::mock(AtelierService::class);
        $mock->shouldReceive('findOwnedByUser')->with(self::ID_COMPTE_ATELIER)->andReturn($atelier);
        $this->app->instance(AtelierService::class, $mock);

        return $mock;
    }

    /**
     * @return RendezVousService&MockInterface
     */
    private function mockRendezVous(AtelierService $ateliers): RendezVousService
    {
        $mock = Mockery::mock(RendezVousService::class, [$ateliers])->makePartial()->shouldAllowMockingProtectedMethods();
        $this->app->instance(RendezVousService::class, $mock);

        return $mock;
    }

    private function rdvAffichable(string $statut, string $id, string $date = '2026-10-06', string $heure = '10:00'): RendezVous
    {
        $rdv = $this->rdv($statut, $date, $heure, 45, self::ID_ATELIER, $id);
        $rdv->setRelation('client', $this->utilisateur(nom: 'Amira <b>Trabelsi</b>'));
        $rdv->setRelation('service', $this->prestation());
        $rdv->setRelation('vetement', $this->vetement());
        $rdv->setRelation('atelier', $this->atelier());

        return $rdv;
    }

    private function page(array $rdvs): LengthAwarePaginator
    {
        return new LengthAwarePaginator(new Collection($rdvs), count($rdvs), 15, 1, ['path' => route('back.rdv')]);
    }

    private function stats(): array
    {
        return ['en_attente' => 3, 'confirme' => 5, 'aujourdhui' => 2, 'termine' => 7];
    }

    /* --------------------------------------------------------------- Accès */

    public function test_un_citoyen_n_accede_pas_aux_rendez_vous_du_back_office(): void
    {
        $ateliers = $this->mockAteliers(null);
        $ateliers->shouldNotReceive('findOwnedByUser');
        $this->mockRendezVous($ateliers)->shouldNotReceive('lister', 'changerStatut');

        $this->actingAs($this->utilisateur());

        $this->get('/admin/rendez-vous')->assertRedirect(route('front.home'));
        $this->patch('/admin/rendez-vous/'.self::ID_RDV.'/statut', ['statut' => 'confirme'])->assertRedirect(route('front.home'));
    }

    public function test_une_association_n_accede_pas_aux_rendez_vous(): void
    {
        $this->mockRendezVous($this->mockAteliers(null))->shouldNotReceive('lister');

        $this->actingAs($this->utilisateur(User::ROLE_ASSOCIATION, '652f000000000000000000c3'))
            ->get('/admin/rendez-vous')
            ->assertForbidden();
    }

    public function test_un_atelier_sans_fiche_voit_l_etat_vide(): void
    {
        $this->mockRendezVous($this->mockAteliers(null))->shouldNotReceive('lister');

        $this->actingAs($this->compteAtelier())
            ->get('/admin/rendez-vous')
            ->assertOk()
            ->assertSee("Votre atelier n'est pas encore configuré.", false);
    }

    /* --------------------------------------------------------------- Liste */

    public function test_un_atelier_ne_voit_que_les_rdv_de_son_atelier_meme_avec_un_autre_id_dans_l_url(): void
    {
        $ateliers = $this->mockAteliers($this->atelier());
        $rendezVous = $this->mockRendezVous($ateliers);
        $rendezVous->shouldReceive('lister')
            ->once()
            ->with(self::ID_ATELIER, ['statut' => RendezVous::STATUT_EN_ATTENTE, 'periode' => RendezVousService::PERIODE_A_VENIR], 15)
            ->andReturn($this->page([
                $this->rdvAffichable(RendezVous::STATUT_EN_ATTENTE, '652f000000000000000000f1', '2026-10-05', '14:00'),
                $this->rdvAffichable(RendezVous::STATUT_CONFIRME, '652f000000000000000000f2'),
                $this->rdvAffichable(RendezVous::STATUT_TERMINE, '652f000000000000000000f3'),
            ]));
        $rendezVous->shouldReceive('statistiques')->once()->with(self::ID_ATELIER)->andReturn($this->stats());
        $rendezVous->shouldNotReceive('ateliersPourFiltre');

        $response = $this->actingAs($this->compteAtelier())
            ->get('/admin/rendez-vous?statut=en_attente&periode=a_venir&atelier='.self::ID_AUTRE_ATELIER);

        $response->assertOk()
            ->assertSee('Rendez-vous de <strong>Couture &lt;script&gt;alert(1)&lt;/script&gt; Atelier</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Amira &lt;b&gt;Trabelsi&lt;/b&gt;', false)
            ->assertSee('Ourlet &lt;b&gt;express&lt;/b&gt;', false)
            ->assertSee('« Ourlet &lt;i&gt;abîmé&lt;/i&gt; »', false)
            ->assertSee('Veste en jean · Taille M')
            ->assertSee('45 min')
            ->assertSee('15 TND')
            ->assertSee('Lundi 5 oct. 2026')
            ->assertSee("Aujourd'hui", false)
            ->assertSee('14:00 – 14:45', false)
            ->assertSee('Date et heure')
            ->assertDontSee('Tous les ateliers')
            ->assertDontSee('name="atelier"', false)
            ->assertSee('css/ateliers-back.css', false);

        $html = $response->getContent();
        foreach (['En attente', 'Confirmés', "Aujourd'hui", 'Terminés'] as $carte) {
            $this->assertStringContainsString('<p class="eyebrow">'.e($carte).'</p>', $html);
        }
        $this->assertSame(1, substr_count($html, 'name="statut" value="confirme"'));
        $this->assertSame(1, substr_count($html, 'name="statut" value="refuse"'));
        $this->assertSame(1, substr_count($html, 'name="statut" value="termine"'));
        $this->assertSame(1, substr_count($html, 'name="statut" value="annule"'));
        $this->assertStringContainsString('Aucune action', $html);
        $this->assertStringContainsString('name="_method" value="PATCH"', $html);
        $this->assertStringContainsString(e(route('back.rdv.statut', ['id' => '652f000000000000000000f1'])), $html);
        $this->assertStringNotContainsString('{!!', $html);
    }

    public function test_l_admin_voit_tous_les_rdv_et_peut_filtrer_par_atelier(): void
    {
        $ateliers = Mockery::mock(AtelierService::class);
        $ateliers->shouldNotReceive('findOwnedByUser');
        $this->app->instance(AtelierService::class, $ateliers);
        $rendezVous = $this->mockRendezVous($ateliers);
        $rendezVous->shouldReceive('lister')->once()->with(null, [], 15)->andReturn($this->page([$this->rdvAffichable(RendezVous::STATUT_EN_ATTENTE, self::ID_RDV)]));
        $rendezVous->shouldReceive('lister')->once()->with(self::ID_AUTRE_ATELIER, ['atelier' => self::ID_AUTRE_ATELIER], 15)->andReturn($this->page([]));
        $rendezVous->shouldReceive('statistiques')->andReturn($this->stats());
        $rendezVous->shouldReceive('ateliersPourFiltre')->andReturn(new Collection([$this->atelier(self::ID_AUTRE_ATELIER)]));

        $this->actingAs($this->admin());

        $this->get('/admin/rendez-vous')
            ->assertOk()
            ->assertSee('Tous les ateliers')
            ->assertSee('<th scope="col">Atelier</th>', false)
            ->assertSee('<option value="'.self::ID_AUTRE_ATELIER.'" >', false);

        $this->get('/admin/rendez-vous?atelier='.self::ID_AUTRE_ATELIER)
            ->assertOk()
            ->assertSee('Aucun rendez-vous ne correspond à ces filtres');
    }

    /* ------------------------------------------------------------- Actions */

    public function test_le_role_atelier_est_autorise_sur_back_rdv_etoile_par_role_navigation_service(): void
    {
        $atelier = $this->atelier();
        $rendezVous = $this->mockRendezVous($this->mockAteliers($atelier));
        $rendezVous->shouldReceive('findPourAtelier')->andReturn($this->rdv()->setRelation('atelier', $atelier));
        $rendezVous->shouldReceive('enregistrer')->once();

        $this->actingAs($this->compteAtelier())
            ->patch('/admin/rendez-vous/'.self::ID_RDV.'/statut', ['statut' => RendezVous::STATUT_CONFIRME])
            ->assertRedirect(route('back.rdv'));
    }

    public function test_un_atelier_confirme_son_propre_rdv(): void
    {
        $this->withoutMiddleware(EnsureBackOfficeRoute::class);
        $atelier = $this->atelier();
        $rendezVous = $this->mockRendezVous($this->mockAteliers($atelier));
        $rendezVous->shouldReceive('findPourAtelier')->once()->with($atelier, self::ID_RDV)->andReturn($this->rdv()->setRelation('atelier', $atelier));
        $rendezVous->shouldReceive('enregistrer')->once()->with(Mockery::on(fn (RendezVous $r) => $r->statut === RendezVous::STATUT_CONFIRME));

        $this->actingAs($this->compteAtelier())
            ->from('/admin/rendez-vous?statut=en_attente')
            ->patch('/admin/rendez-vous/'.self::ID_RDV.'/statut', ['statut' => RendezVous::STATUT_CONFIRME])
            ->assertRedirect('/admin/rendez-vous?statut=en_attente')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Le rendez-vous du mardi 6 oct. 2026 à 10:00 est confirmé.');
    }

    public function test_refuser_exige_un_motif_court(): void
    {
        $this->withoutMiddleware(EnsureBackOfficeRoute::class);
        $atelier = $this->atelier();
        $rendezVous = $this->mockRendezVous($this->mockAteliers($atelier));
        $rendezVous->shouldReceive('findPourAtelier')->andReturn($this->rdv()->setRelation('atelier', $atelier));
        $rendezVous->shouldReceive('enregistrer')->once()->with(Mockery::on(fn (RendezVous $r) => $r->statut === RendezVous::STATUT_REFUSE && $r->motif === 'Atelier fermé ce jour-là'));

        $this->actingAs($this->compteAtelier());

        $this->patch('/admin/rendez-vous/'.self::ID_RDV.'/statut', ['statut' => RendezVous::STATUT_REFUSE])
            ->assertSessionHasErrors(['motif' => 'Indiquez le motif du refus.']);

        $this->patch('/admin/rendez-vous/'.self::ID_RDV.'/statut', ['statut' => RendezVous::STATUT_REFUSE, 'motif' => str_repeat('x', 201)])
            ->assertSessionHasErrors('motif');

        $this->patch('/admin/rendez-vous/'.self::ID_RDV.'/statut', ['statut' => RendezVous::STATUT_REFUSE, 'motif' => 'Atelier fermé ce jour-là'])
            ->assertSessionHas('success', 'Le rendez-vous du mardi 6 oct. 2026 à 10:00 a été refusé.');
    }

    public function test_une_transition_interdite_renvoie_une_erreur_sans_enregistrer(): void
    {
        $this->withoutMiddleware(EnsureBackOfficeRoute::class);
        $atelier = $this->atelier();
        $rendezVous = $this->mockRendezVous($this->mockAteliers($atelier));
        $rendezVous->shouldReceive('findPourAtelier')->andReturn($this->rdv(RendezVous::STATUT_TERMINE)->setRelation('atelier', $atelier));
        $rendezVous->shouldNotReceive('enregistrer');

        $this->actingAs($this->compteAtelier())
            ->patch('/admin/rendez-vous/'.self::ID_RDV.'/statut', ['statut' => RendezVous::STATUT_CONFIRME])
            ->assertSessionHasErrors(['statut' => 'Un rendez-vous « Terminé » ne peut pas passer au statut « Confirmé ».']);

        $this->patch('/admin/rendez-vous/'.self::ID_RDV.'/statut', ['statut' => RendezVous::STATUT_EN_ATTENTE])
            ->assertSessionHasErrors(['statut' => "Cette action n'existe pas."]);
    }

    public function test_le_rdv_d_un_autre_atelier_donne_une_404(): void
    {
        $this->withoutMiddleware(EnsureBackOfficeRoute::class);
        $atelier = $this->atelier();
        $rendezVous = $this->mockRendezVous($this->mockAteliers($atelier));
        $rendezVous->shouldReceive('findPourAtelier')
            ->with($atelier, '652f00000000000000000ff9')
            ->andThrow((new ModelNotFoundException())->setModel(RendezVous::class));
        $rendezVous->shouldNotReceive('changerStatut', 'enregistrer');

        $this->actingAs($this->compteAtelier())
            ->patch('/admin/rendez-vous/652f00000000000000000ff9/statut', ['statut' => RendezVous::STATUT_CONFIRME])
            ->assertNotFound();
    }

    public function test_un_rdv_rattache_a_un_autre_atelier_est_refuse_par_la_policy(): void
    {
        $this->withoutMiddleware(EnsureBackOfficeRoute::class);
        $rendezVous = $this->mockRendezVous($this->mockAteliers($this->atelier()));
        $intrus = $this->atelier(self::ID_AUTRE_ATELIER);
        $intrus->user_id = '652f0000000000000000eeee';
        $rendezVous->shouldReceive('findPourAtelier')->andReturn($this->rdv(atelierId: self::ID_AUTRE_ATELIER)->setRelation('atelier', $intrus));
        $rendezVous->shouldNotReceive('enregistrer');

        $this->actingAs($this->compteAtelier())
            ->patch('/admin/rendez-vous/'.self::ID_RDV.'/statut', ['statut' => RendezVous::STATUT_CONFIRME])
            ->assertForbidden();
    }

    public function test_un_identifiant_qui_n_est_pas_un_object_id_donne_une_404(): void
    {
        $this->withoutMiddleware(EnsureBackOfficeRoute::class);
        $this->mockRendezVous($this->mockAteliers($this->atelier()))->shouldNotReceive('findPourAtelier');

        $this->actingAs($this->compteAtelier())
            ->patch('/admin/rendez-vous/pas-un-id/statut', ['statut' => RendezVous::STATUT_CONFIRME])
            ->assertNotFound();
    }

    public function test_l_admin_peut_marquer_un_rdv_confirme_comme_termine(): void
    {
        $ateliers = Mockery::mock(AtelierService::class);
        $this->app->instance(AtelierService::class, $ateliers);
        $rendezVous = $this->mockRendezVous($ateliers);
        $rendezVous->shouldReceive('findOrFail')->once()->with(self::ID_RDV)->andReturn($this->rdv(RendezVous::STATUT_CONFIRME)->setRelation('atelier', $this->atelier()));
        $rendezVous->shouldReceive('enregistrer')->once();

        $this->actingAs($this->admin())
            ->patch('/admin/rendez-vous/'.self::ID_RDV.'/statut', ['statut' => RendezVous::STATUT_TERMINE])
            ->assertSessionHas('success', 'Le rendez-vous du mardi 6 oct. 2026 à 10:00 est marqué comme terminé.');
    }

    /* ------------------------------------------------- Résumé espace atelier */

    public function test_le_profil_atelier_affiche_le_resume_des_prochains_rdv(): void
    {
        $atelier = $this->atelier();
        $ateliers = Mockery::mock(AtelierService::class)->makePartial();
        $ateliers->shouldReceive('findOwnedByUser')->with(self::ID_COMPTE_ATELIER)->andReturn($atelier);
        $this->app->instance(AtelierService::class, $ateliers);
        $rendezVous = $this->mockRendezVous($ateliers);
        $rendezVous->shouldReceive('resumeAtelier')->once()->with($atelier)->andReturn([
            'en_attente' => 2,
            'prochains' => new Collection([$this->rdvAffichable(RendezVous::STATUT_EN_ATTENTE, self::ID_RDV)]),
        ]);

        $this->actingAs($this->compteAtelier())
            ->get('/admin/ateliers/mon-profil')
            ->assertOk()
            ->assertSee('Prochains rendez-vous')
            ->assertSee('<span class="rdv-resume__count">2</span>', false)
            ->assertSee('demandes en attente de votre réponse')
            ->assertSee(e(route('back.rdv', ['statut' => RendezVous::STATUT_EN_ATTENTE])), false)
            ->assertSee('Mardi 6 oct. 2026 · 10:00')
            ->assertSee('Amira &lt;b&gt;Trabelsi&lt;/b&gt;', false);
    }
}
