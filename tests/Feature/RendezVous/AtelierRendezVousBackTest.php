<?php

namespace Tests\Feature\RendezVous;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Auth\Models\User;
use App\Modules\RendezVous\Models\RendezVous;
use App\Modules\RendezVous\Services\RendezVousService;
use App\Modules\Vetements\Models\Vetement;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

class AtelierRendezVousBackTest extends TestCase
{
    use BlocksRemoteMongo;

    private const ID_USER = '652f000000000000000000c1';

    private const ID_ATELIER = '652f000000000000000000a1';

    private const ID_RDV = '652f000000000000000000f1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
    }

    private function user(string $role = User::ROLE_ATELIER): User
    {
        $user = new User([
            'name' => 'Samia Atelier',
            'email' => 'samia@example.test',
            'role' => $role,
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

    private function mockAtelier(?Atelier $atelier): void
    {
        $mock = Mockery::mock(AtelierService::class)->makePartial();
        $mock->shouldReceive('findOwnedByUser')->andReturn($atelier);
        $this->app->instance(AtelierService::class, $mock);
    }

    public function test_l_atelier_ne_voit_que_ses_rendez_vous(): void
    {
        $this->mockAtelier($this->atelier());

        $vetement = new Vetement(['type' => 'Jean', 'size' => 'M']);
        $rdv = new RendezVous([
            'atelier_id' => self::ID_ATELIER,
            'statut' => RendezVous::STATUT_EN_ATTENTE,
            'duree' => 30,
            'commentaire' => 'Ourlet',
        ]);
        $rdv->setAttribute('_id', self::ID_RDV);
        $rdv->setRelation('vetement', $vetement);
        $rdv->setRelation('user', $this->user(User::ROLE_CITOYEN));

        $service = Mockery::mock(RendezVousService::class);
        $service->shouldReceive('listerPourAtelier')->once()->with(self::ID_ATELIER, null)->andReturn(collect([$rdv]));
        $service->shouldReceive('countsPourAtelier')->once()->with(self::ID_ATELIER)->andReturn([
            'en_attente' => 1, 'confirme' => 0, 'annule' => 0, 'termine' => 0, 'total' => 1,
        ]);
        $service->shouldNotReceive('listerPourAdmin');
        $this->app->instance(RendezVousService::class, $service);

        $this->actingAs($this->user())
            ->get('/admin/rendez-vous')
            ->assertOk()
            ->assertSee('Jean')
            ->assertSee('Ourlet')
            ->assertSee('Confirmer');
    }

    public function test_un_atelier_ne_peut_pas_traiter_le_rendez_vous_d_un_autre(): void
    {
        $this->mockAtelier($this->atelier());

        $rdv = new RendezVous(['atelier_id' => '652f000000000000000000a9', 'statut' => RendezVous::STATUT_EN_ATTENTE]);
        $rdv->setAttribute('_id', self::ID_RDV);

        $service = Mockery::mock(RendezVousService::class);
        $service->shouldReceive('changerStatutPourAtelier')
            ->once()
            ->andReturnUsing(function () {
                abort(403, 'Ce rendez-vous n\'appartient pas à votre atelier.');
            });
        $this->app->instance(RendezVousService::class, $service);

        $this->actingAs($this->user())
            ->patch('/admin/rendez-vous/'.self::ID_RDV.'/statut', ['statut' => RendezVous::STATUT_CONFIRME])
            ->assertForbidden();
    }

    public function test_confirmer_un_rendez_vous_de_l_atelier(): void
    {
        $this->mockAtelier($this->atelier());

        $rdv = new RendezVous(['atelier_id' => self::ID_ATELIER, 'statut' => RendezVous::STATUT_CONFIRME]);
        $rdv->setAttribute('_id', self::ID_RDV);

        $service = Mockery::mock(RendezVousService::class);
        $service->shouldReceive('changerStatutPourAtelier')
            ->once()
            ->with(self::ID_RDV, self::ID_ATELIER, RendezVous::STATUT_CONFIRME)
            ->andReturn($rdv);
        $this->app->instance(RendezVousService::class, $service);

        $this->actingAs($this->user())
            ->from('/admin/rendez-vous')
            ->patch('/admin/rendez-vous/'.self::ID_RDV.'/statut', ['statut' => RendezVous::STATUT_CONFIRME])
            ->assertRedirect('/admin/rendez-vous')
            ->assertSessionHas('success');
    }
}
