<?php

namespace Tests\Feature\Vetements;

use App\Modules\Auth\Models\User;
use App\Modules\Vetements\Models\CycleVieEvent;
use App\Modules\Vetements\Models\Vetement;
use App\Modules\Vetements\Services\VetementService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

class HistoriqueCycleVieTest extends TestCase
{
    use BlocksRemoteMongo;

    private const ID_USER = '652f000000000000000000c9';

    private const ID_VETEMENT = '652f000000000000000000e9';

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
    }

    private function citoyen(): User
    {
        $user = new User([
            'name' => 'Yasmine Ben Ali',
            'email' => 'yasmine@example.test',
            'role' => User::ROLE_CITOYEN,
            'is_active' => true,
        ]);
        $user->setAttribute('_id', self::ID_USER);

        return $user;
    }

    private function evenement(string $step, int $ordre, string $titre, string $description, string $statut, string $date): CycleVieEvent
    {
        $event = new CycleVieEvent([
            'step_key' => $step,
            'step_order' => $ordre,
            'title' => $titre,
            'description' => $description,
            'status_snapshot' => $statut,
            'occurred_at' => Carbon::parse($date),
        ]);
        $event->setAttribute('_id', sprintf('652f00000000000000000f%02d', $ordre));

        return $event;
    }

    private function vetementHistorise(): Vetement
    {
        $vetement = new Vetement([
            'type' => 'Jean droit',
            'size' => 'M',
            'material' => 'Denim',
            'status' => Vetement::STATUS_REPARE,
            'intended_action' => Vetement::ACTION_REPARATION,
        ]);
        $vetement->setAttribute('_id', self::ID_VETEMENT);
        $vetement->setRelation('cycleEvents', new Collection([
            $this->evenement(CycleVieEvent::STEP_DECLARE, 1, 'Déclaré', 'Vêtement ajouté à votre espace TexTileCycle.', Vetement::STATUS_EN_ATTENTE, '2026-09-01 09:00:00'),
            $this->evenement(CycleVieEvent::STEP_ANALYSE, 2, 'Parcours réparation', 'Vous souhaitez faire réparer cette pièce en atelier.', Vetement::STATUS_EN_ATTENTE, '2026-09-01 09:05:00'),
            $this->evenement(CycleVieEvent::STEP_ACTION, 3, 'Réparation en cours', 'L\'atelier a pris en charge cette pièce.', Vetement::STATUS_EN_REPARATION, '2026-09-10 14:00:00'),
            $this->evenement(CycleVieEvent::STEP_TERMINE, 4, 'Réparé', 'La réparation est terminée.', Vetement::STATUS_REPARE, '2026-09-12 16:30:00'),
        ]));

        return $vetement;
    }

    public function test_un_invite_est_redirige_vers_la_connexion(): void
    {
        $this->get('/mon-historique')->assertRedirect('/connexion');
    }

    public function test_l_historique_vide_explique_le_parcours(): void
    {
        $service = Mockery::mock(VetementService::class);
        $service->shouldReceive('listHistoriqueForOwner')->once()->andReturn(collect());
        $this->app->instance(VetementService::class, $service);

        $this->actingAs($this->citoyen())
            ->get('/mon-historique')
            ->assertOk()
            ->assertSee('Cycle de vie', false)
            ->assertSee('Aucun cycle clôturé pour le moment', false)
            ->assertSee('Historique', false);
    }

    public function test_une_piece_reparee_apparait_avec_son_historique_complet(): void
    {
        $service = Mockery::mock(VetementService::class);
        $service->shouldReceive('listHistoriqueForOwner')
            ->once()
            ->andReturn(collect([$this->vetementHistorise()]));
        $this->app->instance(VetementService::class, $service);

        $this->actingAs($this->citoyen())
            ->get('/mon-historique')
            ->assertOk()
            ->assertSee('Jean droit', false)
            ->assertSee('Déclaré', false)
            ->assertSee('Parcours réparation', false)
            ->assertSee('Réparation en cours', false)
            ->assertSee('Réparé', false)
            ->assertSee('La réparation est terminée.', false)
            ->assertSee('12/09/2026', false);
    }

    public function test_le_filtre_don_est_transmis_au_service(): void
    {
        $service = Mockery::mock(VetementService::class);
        $service->shouldReceive('listHistoriqueForOwner')
            ->once()
            ->with(Mockery::type(User::class), Vetement::STATUS_DONNE)
            ->andReturn(collect());
        $this->app->instance(VetementService::class, $service);

        $this->actingAs($this->citoyen())
            ->get('/mon-historique?statut=donne')
            ->assertOk();
    }

    public function test_mes_vetements_ne_liste_que_les_pieces_actives(): void
    {
        $actif = new Vetement([
            'type' => 'Chemise lin',
            'size' => 'S',
            'status' => Vetement::STATUS_EN_ATTENTE,
            'intended_action' => Vetement::ACTION_REPARATION,
        ]);
        $actif->setAttribute('_id', '652f000000000000000000ea');
        $actif->setRelation('cycleEvents', new Collection());

        $service = Mockery::mock(VetementService::class);
        $service->shouldReceive('listActifsForOwner')->once()->andReturn(collect([$actif]));
        $service->shouldNotReceive('listHistoriqueForOwner');
        $this->app->instance(VetementService::class, $service);

        $this->actingAs($this->citoyen())
            ->get('/mes-vetements')
            ->assertOk()
            ->assertSee('Chemise lin', false)
            ->assertSee('Historique', false)
            ->assertDontSee('Jean droit', false);
    }
}
