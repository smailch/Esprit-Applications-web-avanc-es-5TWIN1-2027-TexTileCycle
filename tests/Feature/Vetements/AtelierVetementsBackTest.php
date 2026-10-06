<?php

namespace Tests\Feature\Vetements;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Auth\Models\User;
use App\Modules\Vetements\Models\Vetement;
use App\Modules\Vetements\Services\VetementService;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

class AtelierVetementsBackTest extends TestCase
{
    use BlocksRemoteMongo;

    private const ID_USER = '652f000000000000000000c1';

    private const ID_ATELIER = '652f000000000000000000a1';

    private const ID_VETEMENT = '652f000000000000000000e1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
    }

    private function user(): User
    {
        $user = new User([
            'name' => 'Samia Atelier',
            'email' => 'samia@example.test',
            'role' => User::ROLE_ATELIER,
            'is_active' => true,
        ]);
        $user->setAttribute('_id', self::ID_USER);

        return $user;
    }

    private function atelier(): Atelier
    {
        $atelier = new Atelier(['nom' => 'Couture Medina', 'ville' => 'Tunis', 'statut' => Atelier::STATUT_ACTIF]);
        $atelier->setAttribute('_id', self::ID_ATELIER);
        $atelier->user_id = self::ID_USER;
        $atelier->setRelation('services', new Collection());

        return $atelier;
    }

    private function mockAtelier(): void
    {
        $mock = Mockery::mock(AtelierService::class)->makePartial();
        $mock->shouldReceive('findOwnedByUser')->andReturn($this->atelier());
        $this->app->instance(AtelierService::class, $mock);
    }

    public function test_l_atelier_ne_voit_que_ses_pieces(): void
    {
        $this->mockAtelier();

        $vetement = new Vetement([
            'type' => 'Veste denim',
            'size' => 'L',
            'status' => Vetement::STATUS_EN_ATTENTE,
            'atelier_id' => self::ID_ATELIER,
            'intended_action' => Vetement::ACTION_REPARATION,
        ]);
        $vetement->setAttribute('_id', self::ID_VETEMENT);
        $vetement->setRelation('owner', new User(['name' => 'Yasmine Ben Ali']));
        $vetement->setRelation('cycleEvents', new Collection());

        $service = Mockery::mock(VetementService::class);
        $service->shouldReceive('listForAtelier')->once()->with(self::ID_ATELIER)->andReturn(collect([$vetement]));
        $service->shouldReceive('countsForAtelier')->once()->with(self::ID_ATELIER)->andReturn([
            'en_attente' => 1, 'en_reparation' => 0, 'repare' => 0, 'donne' => 0, 'recycle' => 0, 'total' => 1,
        ]);
        $service->shouldNotReceive('listAllForBackOffice');
        $this->app->instance(VetementService::class, $service);

        $this->actingAs($this->user())
            ->get('/admin/vetements')
            ->assertOk()
            ->assertSee('Veste denim')
            ->assertSee('Yasmine Ben Ali')
            ->assertSee('Démarrer la réparation')
            ->assertDontSee('listAllForBackOffice');
    }

    public function test_demarrer_la_reparation_d_une_piece_de_l_atelier(): void
    {
        $this->mockAtelier();

        $vetement = new Vetement(['status' => Vetement::STATUS_EN_REPARATION, 'atelier_id' => self::ID_ATELIER]);
        $service = Mockery::mock(VetementService::class);
        $service->shouldReceive('traiterPourAtelier')
            ->once()
            ->with(self::ID_VETEMENT, self::ID_ATELIER, Vetement::STATUS_EN_REPARATION)
            ->andReturn($vetement);
        $this->app->instance(VetementService::class, $service);

        $this->actingAs($this->user())
            ->from('/admin/vetements')
            ->patch('/admin/vetements/'.self::ID_VETEMENT.'/traiter', ['status' => Vetement::STATUS_EN_REPARATION])
            ->assertRedirect('/admin/vetements')
            ->assertSessionHas('success');
    }
}
