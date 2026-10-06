<?php

namespace Tests\Feature\Ateliers;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Auth\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

/**
 * Policies testées via Gate (donc via Gate::policy() du provider), avec des modèles en mémoire.
 */
class AteliersPoliciesTest extends TestCase
{
    use BlocksRemoteMongo;

    private const ID_PROPRIO = '652f00000000000000000001';

    private const ID_AUTRE = '652f00000000000000000002';

    private const ID_ATELIER = '652f0000000000000000000a';

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
    }

    private function user(string $role, string $id = '652f00000000000000000099'): User
    {
        $user = new User(['name' => $role, 'role' => $role]);
        $user->setAttribute('_id', $id);

        return $user;
    }

    private function atelier(string $userId = self::ID_PROPRIO): Atelier
    {
        $atelier = new Atelier(['nom' => 'Couture Plus']);
        $atelier->setAttribute('_id', self::ID_ATELIER);
        $atelier->user_id = $userId;

        return $atelier;
    }

    private function service(?Atelier $atelier): Service
    {
        $service = new Service(['nom' => 'Ourlet']);
        $service->atelier_id = $atelier ? (string) $atelier->getKey() : null;

        return $atelier ? $service->setRelation('atelier', $atelier) : $service;
    }

    public function test_les_policies_sont_enregistrees(): void
    {
        $this->assertInstanceOf(\App\Modules\Ateliers\Policies\AtelierPolicy::class, Gate::getPolicyFor(Atelier::class));
        $this->assertInstanceOf(\App\Modules\Ateliers\Policies\ServicePolicy::class, Gate::getPolicyFor(Service::class));
    }

    public function test_admin_peut_tout_faire_sur_les_ateliers(): void
    {
        $gate = Gate::forUser($this->user(User::ROLE_ADMIN));
        $atelier = $this->atelier();

        $this->assertTrue($gate->allows('viewAny', Atelier::class));
        $this->assertTrue($gate->allows('create', Atelier::class));
        $this->assertTrue($gate->allows('view', $atelier));
        $this->assertTrue($gate->allows('update', $atelier));
        $this->assertTrue($gate->allows('delete', $atelier));
        $this->assertTrue($gate->allows('changerStatut', $atelier));
    }

    public function test_atelier_voit_et_modifie_uniquement_le_sien(): void
    {
        $gate = Gate::forUser($this->user(User::ROLE_ATELIER, self::ID_PROPRIO));

        $this->assertTrue($gate->allows('view', $this->atelier()));
        $this->assertTrue($gate->allows('update', $this->atelier()));
        $this->assertFalse($gate->allows('view', $this->atelier(self::ID_AUTRE)));
        $this->assertFalse($gate->allows('update', $this->atelier(self::ID_AUTRE)));
    }

    public function test_atelier_ne_peut_ni_creer_ni_supprimer_ni_changer_le_statut(): void
    {
        $gate = Gate::forUser($this->user(User::ROLE_ATELIER, self::ID_PROPRIO));

        $this->assertFalse($gate->allows('viewAny', Atelier::class));
        $this->assertFalse($gate->allows('create', Atelier::class));
        $this->assertFalse($gate->allows('delete', $this->atelier()));
        $this->assertFalse($gate->allows('changerStatut', $this->atelier()));
    }

    public function test_un_citoyen_avec_le_meme_id_n_est_pas_proprietaire(): void
    {
        $gate = Gate::forUser($this->user(User::ROLE_CITOYEN, self::ID_PROPRIO));

        $this->assertFalse($gate->allows('view', $this->atelier()));
        $this->assertFalse($gate->allows('update', $this->atelier()));
    }

    public function test_atelier_sans_user_id_n_appartient_a_personne(): void
    {
        $atelier = new Atelier(['nom' => 'Orphelin']);

        $this->assertFalse(Gate::forUser($this->user(User::ROLE_ATELIER, self::ID_PROPRIO))->allows('update', $atelier));
    }

    public function test_service_admin_peut_tout_faire(): void
    {
        $gate = Gate::forUser($this->user(User::ROLE_ADMIN));
        $service = $this->service($this->atelier(self::ID_AUTRE));

        $this->assertTrue($gate->allows('viewAny', Service::class));
        $this->assertTrue($gate->allows('create', [Service::class, $this->atelier(self::ID_AUTRE)]));
        $this->assertTrue($gate->allows('update', $service));
        $this->assertTrue($gate->allows('delete', $service));
    }

    public function test_service_atelier_gere_uniquement_les_services_de_son_atelier(): void
    {
        $gate = Gate::forUser($this->user(User::ROLE_ATELIER, self::ID_PROPRIO));
        $sien = $this->service($this->atelier());
        $autre = $this->service($this->atelier(self::ID_AUTRE));

        $this->assertTrue($gate->allows('viewAny', Service::class));
        $this->assertTrue($gate->allows('view', $sien));
        $this->assertTrue($gate->allows('update', $sien));
        $this->assertTrue($gate->allows('delete', $sien));
        $this->assertFalse($gate->allows('view', $autre));
        $this->assertFalse($gate->allows('update', $autre));
        $this->assertFalse($gate->allows('delete', $autre));
    }

    public function test_service_creation_seulement_dans_son_atelier(): void
    {
        $gate = Gate::forUser($this->user(User::ROLE_ATELIER, self::ID_PROPRIO));

        $this->assertTrue($gate->allows('create', [Service::class, $this->atelier()]));
        $this->assertFalse($gate->allows('create', [Service::class, $this->atelier(self::ID_AUTRE)]));
        $this->assertFalse($gate->allows('create', Service::class));
    }

    public function test_service_dont_l_atelier_charge_ne_correspond_pas_a_atelier_id(): void
    {
        $service = $this->service($this->atelier());
        $service->atelier_id = '652f0000000000000000000b';

        $this->assertFalse(Gate::forUser($this->user(User::ROLE_ATELIER, self::ID_PROPRIO))->allows('update', $service));
    }

    public function test_service_refuse_aux_citoyens_et_associations(): void
    {
        foreach ([User::ROLE_CITOYEN, User::ROLE_ASSOCIATION] as $role) {
            $gate = Gate::forUser($this->user($role, self::ID_PROPRIO));

            $this->assertFalse($gate->allows('viewAny', Service::class));
            $this->assertFalse($gate->allows('update', $this->service($this->atelier())));
            $this->assertFalse($gate->allows('update', $this->service(null)));
        }
    }
}
