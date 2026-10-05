<?php

namespace Tests\Unit\RendezVous;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\RendezVous\Models\RendezVous;
use App\Modules\RendezVous\Services\RendezVousService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\Concerns\FabriqueRendezVous;
use Tests\TestCase;

/**
 * Règles métier de RendezVousService. Les accès base (protégés) sont simulés.
 */
class RendezVousServiceTest extends TestCase
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
    private function rdvService(array $rdvsDuJour = [], ?AtelierService $ateliers = null): RendezVousService
    {
        $mock = Mockery::mock(RendezVousService::class, [$ateliers ?? Mockery::mock(AtelierService::class)])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('rdvsOccupantLaJournee')->andReturn(new Collection($rdvsDuJour));

        return $mock;
    }

    /* ------------------------------------------------------------ Horaires */

    public function test_un_creneau_dans_une_plage_d_ouverture_est_accepte(): void
    {
        $this->assertNull($this->rdvService()->verifierCreneau($this->atelier(), '2026-10-06', '14:00', 45));
        $this->assertNull($this->rdvService()->verifierCreneau($this->atelier(), '2026-10-06', '11:15', 45));
    }

    public function test_un_creneau_qui_deborde_de_la_plage_est_refuse(): void
    {
        $erreur = $this->rdvService()->verifierCreneau($this->atelier(), '2026-10-06', '11:30', 45);

        $this->assertSame('heure', $erreur[0]);
        $this->assertStringContainsString("horaires d'ouverture du mardi : 09:00 – 12:00, 14:00 – 18:00", $erreur[1]);
    }

    public function test_un_creneau_pendant_la_pause_ou_avant_l_ouverture_est_refuse(): void
    {
        $this->assertSame('heure', $this->rdvService()->verifierCreneau($this->atelier(), '2026-10-06', '12:30', 30)[0]);
        $this->assertSame('heure', $this->rdvService()->verifierCreneau($this->atelier(), '2026-10-06', '08:30', 30)[0]);
    }

    public function test_un_jour_de_fermeture_est_refuse_avec_le_jour_en_francais(): void
    {
        $erreur = $this->rdvService()->verifierCreneau($this->atelier(), '2026-10-11', '10:00', 30);

        $this->assertSame(['heure', "L'atelier est fermé le dimanche. Choisissez un autre jour."], $erreur);
    }

    public function test_une_date_passee_ou_l_heure_deja_ecoulee_est_refusee(): void
    {
        $this->assertSame('date', $this->rdvService()->verifierCreneau($this->atelier(), '2026-10-02', '10:00', 30)[0]);
        $this->assertSame('date', $this->rdvService()->verifierCreneau($this->atelier(), '2026-10-05', '09:30', 30)[0]);
        $this->assertNull($this->rdvService()->verifierCreneau($this->atelier(), '2026-10-05', '10:30', 30));
    }

    public function test_une_date_impossible_est_refusee(): void
    {
        $this->assertSame('date', $this->rdvService()->verifierCreneau($this->atelier(), '2026-02-30', '10:00', 30)[0]);
    }

    /* ------------------------------------------------------ Chevauchement */

    public function test_un_creneau_qui_chevauche_un_rdv_non_annule_est_refuse(): void
    {
        $existant = $this->rdv(RendezVous::STATUT_CONFIRME, '2026-10-06', '10:00', 45);

        $erreur = $this->rdvService([$existant])->verifierCreneau($this->atelier(), '2026-10-06', '10:30', 30);

        $this->assertSame('heure', $erreur[0]);
        $this->assertStringContainsString('chevauche un autre rendez-vous', $erreur[1]);
        $this->assertStringContainsString('10:00 – 10:45', $erreur[1]);
    }

    public function test_un_creneau_qui_englobe_un_rdv_en_attente_est_refuse(): void
    {
        $existant = $this->rdv(RendezVous::STATUT_EN_ATTENTE, '2026-10-06', '15:00', 15);

        $this->assertNotNull($this->rdvService([$existant])->verifierCreneau($this->atelier(), '2026-10-06', '14:45', 60));
    }

    public function test_des_creneaux_bout_a_bout_ne_se_chevauchent_pas(): void
    {
        $existant = $this->rdv(RendezVous::STATUT_CONFIRME, '2026-10-06', '10:00', 45);

        $this->assertNull($this->rdvService([$existant])->verifierCreneau($this->atelier(), '2026-10-06', '10:45', 30));
        $this->assertNull($this->rdvService([$existant])->verifierCreneau($this->atelier(), '2026-10-06', '09:15', 45));
    }

    public function test_un_rdv_annule_ou_refuse_libere_le_creneau(): void
    {
        $rdvs = [
            $this->rdv(RendezVous::STATUT_ANNULE, '2026-10-06', '10:00', 45),
            $this->rdv(RendezVous::STATUT_REFUSE, '2026-10-06', '10:00', 45),
        ];

        $this->assertNull($this->rdvService($rdvs)->verifierCreneau($this->atelier(), '2026-10-06', '10:00', 45));
    }

    /* ------------------------------------------------- Vérification demande */

    public function test_un_atelier_non_actif_est_refuse(): void
    {
        $ateliers = Mockery::mock(AtelierService::class);
        $ateliers->shouldReceive('findActifOrFail')->with(self::ID_AUTRE_ATELIER)->andThrow(new ModelNotFoundException());

        $resultat = $this->rdvService([], $ateliers)->verifierDemande(self::ID_CITOYEN, [
            'atelier' => self::ID_AUTRE_ATELIER, 'service' => self::ID_SERVICE, 'date' => '2026-10-06', 'heure' => '10:00',
        ]);

        $this->assertSame(['atelier' => "Cet atelier n'est pas disponible à la réservation."], $resultat['erreurs']);
    }

    public function test_un_service_d_un_autre_atelier_est_refuse(): void
    {
        $ateliers = Mockery::mock(AtelierService::class);
        $ateliers->shouldReceive('findActifOrFail')->andReturn($this->atelier());
        $service = $this->rdvService([], $ateliers);
        $service->shouldReceive('trouverService')->once()->with(self::ID_ATELIER, self::ID_SERVICE_ETRANGER)->andReturnNull();

        $resultat = $service->verifierDemande(self::ID_CITOYEN, [
            'atelier' => self::ID_ATELIER, 'service' => self::ID_SERVICE_ETRANGER, 'date' => '2026-10-06', 'heure' => '10:00',
        ]);

        $this->assertSame(['service' => "Ce service n'est pas proposé par cet atelier."], $resultat['erreurs']);
    }

    public function test_un_vetement_qui_n_appartient_pas_au_citoyen_est_refuse(): void
    {
        $ateliers = Mockery::mock(AtelierService::class);
        $ateliers->shouldReceive('findActifOrFail')->andReturn($this->atelier());
        $service = $this->rdvService([], $ateliers);
        $service->shouldReceive('trouverService')->andReturn($this->prestation());
        $service->shouldReceive('trouverVetementReparable')->once()->with(self::ID_CITOYEN, self::ID_VETEMENT)->andReturnNull();

        $resultat = $service->verifierDemande(self::ID_CITOYEN, [
            'atelier' => self::ID_ATELIER, 'service' => self::ID_SERVICE, 'vetement' => self::ID_VETEMENT, 'date' => '2026-10-06', 'heure' => '10:00',
        ]);

        $this->assertArrayHasKey('vetement', $resultat['erreurs']);
    }

    public function test_la_duree_du_service_sert_au_controle_des_horaires(): void
    {
        $ateliers = Mockery::mock(AtelierService::class);
        $ateliers->shouldReceive('findActifOrFail')->andReturn($this->atelier());
        $service = $this->rdvService([], $ateliers);
        $service->shouldReceive('trouverService')->andReturn($this->prestation(self::ID_SERVICE, 'Upcycling', 120));

        $resultat = $service->verifierDemande(self::ID_CITOYEN, [
            'atelier' => self::ID_ATELIER, 'service' => self::ID_SERVICE, 'date' => '2026-10-06', 'heure' => '10:30',
        ]);

        $this->assertArrayHasKey('heure', $resultat['erreurs']);
        $this->assertStringContainsString('(2 h)', $resultat['erreurs']['heure']);
    }

    /* ----------------------------------------------------------- Création */

    public function test_la_creation_stocke_des_identifiants_en_chaine_et_le_statut_en_attente(): void
    {
        $service = $this->rdvService();
        $service->shouldReceive('enregistrer')->once();

        $rdv = $service->creer(self::ID_CITOYEN, $this->atelier(), $this->prestation(), $this->vetement(), [
            'date' => '2026-10-06', 'heure' => '10:00', 'notes' => '  Ourlet  ', 'statut' => RendezVous::STATUT_CONFIRME, 'user_id' => 'pirate',
        ]);

        $this->assertSame(self::ID_CITOYEN, $rdv->user_id);
        $this->assertSame(self::ID_ATELIER, $rdv->atelier_id);
        $this->assertSame(self::ID_SERVICE, $rdv->service_id);
        $this->assertSame(self::ID_VETEMENT, $rdv->vetement_id);
        $this->assertSame(45, $rdv->duree_minutes);
        $this->assertSame('Ourlet', $rdv->notes);
        $this->assertSame(RendezVous::STATUT_EN_ATTENTE, $rdv->statut);
    }

    /* --------------------------------------------------------- Transitions */

    public function transitionsAutorisees(): array
    {
        return [
            'en attente → confirmé' => [RendezVous::STATUT_EN_ATTENTE, RendezVous::STATUT_CONFIRME],
            'en attente → refusé' => [RendezVous::STATUT_EN_ATTENTE, RendezVous::STATUT_REFUSE],
            'confirmé → terminé' => [RendezVous::STATUT_CONFIRME, RendezVous::STATUT_TERMINE],
            'confirmé → annulé' => [RendezVous::STATUT_CONFIRME, RendezVous::STATUT_ANNULE],
        ];
    }

    /**
     * @dataProvider transitionsAutorisees
     */
    public function test_transition_autorisee(string $de, string $vers): void
    {
        $service = $this->rdvService();
        $service->shouldReceive('enregistrer')->once();

        $rdv = $service->changerStatut($this->rdv($de), $vers, 'Pas de disponibilité', $this->atelier());

        $this->assertSame($vers, $rdv->statut);
    }

    public function transitionsInterdites(): array
    {
        return [
            'en attente → terminé' => [RendezVous::STATUT_EN_ATTENTE, RendezVous::STATUT_TERMINE],
            'en attente → annulé' => [RendezVous::STATUT_EN_ATTENTE, RendezVous::STATUT_ANNULE],
            'confirmé → refusé' => [RendezVous::STATUT_CONFIRME, RendezVous::STATUT_REFUSE],
            'confirmé → en attente' => [RendezVous::STATUT_CONFIRME, RendezVous::STATUT_EN_ATTENTE],
            'refusé → confirmé' => [RendezVous::STATUT_REFUSE, RendezVous::STATUT_CONFIRME],
            'terminé → annulé' => [RendezVous::STATUT_TERMINE, RendezVous::STATUT_ANNULE],
            'annulé → confirmé' => [RendezVous::STATUT_ANNULE, RendezVous::STATUT_CONFIRME],
            'statut inconnu' => [RendezVous::STATUT_EN_ATTENTE, 'pirate'],
        ];
    }

    /**
     * @dataProvider transitionsInterdites
     */
    public function test_transition_interdite(string $de, string $vers): void
    {
        $service = $this->rdvService();
        $service->shouldNotReceive('enregistrer');

        try {
            $service->changerStatut($this->rdv($de), $vers, 'motif', $this->atelier());
            $this->fail('La transition aurait dû être refusée.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('statut', $e->errors());
        }
    }

    public function test_un_refus_sans_motif_est_rejete_et_le_motif_est_enregistre_sinon(): void
    {
        $service = $this->rdvService();
        $service->shouldReceive('enregistrer')->once();

        try {
            $service->changerStatut($this->rdv(), RendezVous::STATUT_REFUSE, '   ');
            $this->fail('Le refus sans motif aurait dû être rejeté.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('motif', $e->errors());
        }

        $rdv = $service->changerStatut($this->rdv(), RendezVous::STATUT_REFUSE, '  Atelier complet  ');
        $this->assertSame('Atelier complet', $rdv->motif);
    }

    public function test_le_rdv_d_un_autre_atelier_ne_peut_pas_etre_modifie(): void
    {
        $service = $this->rdvService();
        $service->shouldNotReceive('enregistrer');

        $this->expectException(ModelNotFoundException::class);

        $service->changerStatut($this->rdv(atelierId: self::ID_AUTRE_ATELIER), RendezVous::STATUT_CONFIRME, null, $this->atelier());
    }

    /* -------------------------------------------------------------- Modèle */

    public function test_libelles_tons_et_dates_du_modele(): void
    {
        $rdv = $this->rdv(RendezVous::STATUT_CONFIRME, '2026-10-06', '11:15', 45);

        $this->assertSame('Confirmé', $rdv->statutLabel());
        $this->assertSame('blue', $rdv->statutTone());
        $this->assertSame('Mardi 6 oct. 2026', $rdv->dateFormatee());
        $this->assertSame('12:00', $rdv->heureFin());
        $this->assertSame('orange', $this->rdv()->statutTone());
        $this->assertSame('green', $this->rdv(RendezVous::STATUT_TERMINE)->statutTone());
        $this->assertNull(RendezVous::moment('2026-10-06', '25:00'));
    }

    public function test_la_liste_a_venir_est_triee_par_date_et_limitee_a_l_atelier(): void
    {
        $builder = (new RendezVousService(Mockery::mock(AtelierService::class)))
            ->requeteListe(self::ID_ATELIER, ['periode' => RendezVousService::PERIODE_A_VENIR, 'statut' => 'pirate']);
        $query = $builder->getQuery();

        $this->assertSame([['column' => 'date', 'direction' => 1], ['column' => 'heure', 'direction' => 1]], array_map(
            fn ($o) => ['column' => $o, 'direction' => $query->orders[$o]],
            array_keys($query->orders)
        ));
        $wheres = json_encode($query->wheres);
        $this->assertStringContainsString(self::ID_ATELIER, $wheres);
        $this->assertStringNotContainsString('pirate', $wheres);
        $this->assertStringContainsString('2026-10-05', $wheres);
    }

    public function test_un_atelier_actif_est_reservable_et_un_identifiant_invalide_ne_touche_pas_la_base(): void
    {
        $ateliers = Mockery::mock(AtelierService::class);
        $ateliers->shouldReceive('findActifOrFail')->once()->with(self::ID_ATELIER)->andReturn($this->atelier());

        $service = new RendezVousService($ateliers);

        $this->assertInstanceOf(Atelier::class, $service->atelierReservable(self::ID_ATELIER));
        $this->assertNull($service->atelierReservable('pas-un-id'));
        $this->assertNull($service->atelierReservable(['tableau']));
    }
}
