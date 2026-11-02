<?php

namespace Tests\Feature\Associations;

use App\Modules\Associations\Models\Association;
use App\Modules\Associations\Services\AssociationService;
use App\Modules\Auth\Models\User;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

class PremiereFicheAssociationTest extends TestCase
{
    use BlocksRemoteMongo;

    private const ID_USER = '652f000000000000000000c4';

    private const ID_ASSO = '652f000000000000000000d4';

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
    }

    private function user(): User
    {
        $user = new User([
            'name' => 'Association Verte',
            'email' => 'asso@example.test',
            'role' => User::ROLE_ASSOCIATION,
            'is_active' => true,
        ]);
        $user->setAttribute('_id', self::ID_USER);

        return $user;
    }

    private function mockAssociations(?Association $owned): AssociationService
    {
        $mock = Mockery::mock(AssociationService::class);
        $mock->shouldReceive('findOwnedByUser')->andReturn($owned);
        $this->app->instance(AssociationService::class, $mock);

        return $mock;
    }

    public function test_sans_fiche_le_dashboard_redirige_vers_la_creation(): void
    {
        $this->mockAssociations(null);

        $this->actingAs($this->user())
            ->get('/admin')
            ->assertRedirect(route('back.associations.create'));

        $this->actingAs($this->user())
            ->get('/admin/dons')
            ->assertRedirect(route('back.associations.create'))
            ->assertSessionHas('info');
    }

    public function test_le_formulaire_de_premiere_fiche_s_affiche(): void
    {
        $this->mockAssociations(null);

        $this->actingAs($this->user())
            ->get('/admin/associations/creer')
            ->assertOk()
            ->assertSee('Créer mon association', false)
            ->assertSee('en attente', false)
            ->assertDontSee('name="statut"', false);
    }

    public function test_l_association_peut_creer_sa_fiche_sans_choisir_le_statut(): void
    {
        $created = new Association([
            'nom' => 'Solidarité Mode',
            'statut' => Association::STATUT_EN_ATTENTE,
            'user_id' => self::ID_USER,
        ]);
        $created->setAttribute('_id', self::ID_ASSO);

        $service = $this->mockAssociations(null);
        $service->shouldReceive('normaliserBesoins')->andReturn([]);
        $service->shouldReceive('createOwn')
            ->once()
            ->with(self::ID_USER, Mockery::on(fn (array $data) => ($data['nom'] ?? null) === 'Solidarité Mode'
                && ! array_key_exists('statut', $data)))
            ->andReturn($created);

        $this->actingAs($this->user())
            ->post('/admin/associations', [
                'nom' => 'Solidarité Mode',
                'description' => 'Collecte de textiles',
                'adresse' => '12 Rue de la République, Tunis',
                'telephone' => '+216 71 000 000',
            ])
            ->assertRedirect(route('back.associations'))
            ->assertSessionHas('success');
    }

    public function test_une_seconde_fiche_est_refusee(): void
    {
        $owned = new Association(['nom' => 'Déjà là', 'user_id' => self::ID_USER]);
        $owned->setAttribute('_id', self::ID_ASSO);

        $this->mockAssociations($owned);

        $this->actingAs($this->user())
            ->get('/admin/associations/creer')
            ->assertRedirect(route('back.associations.edit', self::ID_ASSO));
    }

    public function test_create_own_refuse_un_doublon(): void
    {
        $owned = new Association(['nom' => 'Déjà là', 'user_id' => self::ID_USER]);
        $service = Mockery::mock(AssociationService::class)->makePartial();
        $service->shouldReceive('findOwnedByUser')->andReturn($owned);

        $this->expectException(ValidationException::class);
        $service->createOwn(self::ID_USER, ['nom' => 'Autre']);
    }
}
