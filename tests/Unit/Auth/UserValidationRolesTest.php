<?php

namespace Tests\Unit\Auth;

use App\Modules\Auth\Models\User;
use Tests\TestCase;

class UserValidationRolesTest extends TestCase
{
    public function test_inscription_publique_exclut_l_administrateur(): void
    {
        $this->assertSame(
            [User::ROLE_CITOYEN, User::ROLE_ATELIER, User::ROLE_ASSOCIATION],
            User::ROLES_INSCRIPTION
        );
        $this->assertNotContains(User::ROLE_ADMIN, User::ROLES_INSCRIPTION);
    }

    public function test_atelier_et_association_restent_inactifs_a_l_inscription(): void
    {
        $this->assertTrue(User::isActiveOnRegistration(User::ROLE_CITOYEN));
        $this->assertTrue(User::isActiveOnRegistration(User::ROLE_ADMIN));
        $this->assertFalse(User::isActiveOnRegistration(User::ROLE_ATELIER));
        $this->assertFalse(User::isActiveOnRegistration(User::ROLE_ASSOCIATION));
    }

    public function test_compte_atelier_inactif_ne_peut_pas_acceder_au_back_office(): void
    {
        $user = new User([
            'name' => 'Atelier',
            'email' => 'atelier@test.tn',
            'role' => User::ROLE_ATELIER,
            'is_active' => false,
        ]);

        $this->assertFalse($user->isActive());
        $this->assertTrue($user->needsAdministrativeValidation());
        $this->assertTrue($user->isPendingValidation());
        $this->assertFalse($user->canAccessBackOffice());
        $this->assertStringContainsString('validation administrative', $user->inactiveAccountMessage());
    }

    public function test_compte_atelier_valide_accede_au_back_office(): void
    {
        $user = new User([
            'name' => 'Atelier',
            'email' => 'atelier@test.tn',
            'role' => User::ROLE_ATELIER,
            'is_active' => true,
        ]);

        $this->assertTrue($user->isActive());
        $this->assertFalse($user->isPendingValidation());
        $this->assertTrue($user->canAccessBackOffice());
    }

    public function test_document_sans_is_active_reste_considere_actif(): void
    {
        $user = new User(['name' => 'Amina', 'email' => 'a@test.tn', 'role' => User::ROLE_CITOYEN]);
        $user->offsetUnset('is_active');

        $this->assertTrue($user->isActive());
    }
}
