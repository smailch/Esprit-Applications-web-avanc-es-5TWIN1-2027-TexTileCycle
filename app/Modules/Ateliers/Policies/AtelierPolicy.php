<?php

namespace App\Modules\Ateliers\Policies;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Auth\Models\User;

class AtelierPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, Atelier $atelier): bool
    {
        return self::owns($user, $atelier);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Atelier $atelier): bool
    {
        return self::owns($user, $atelier);
    }

    public function delete(User $user, Atelier $atelier): bool
    {
        return false;
    }

    public function changerStatut(User $user, Atelier $atelier): bool
    {
        return false;
    }

    public static function owns(User $user, ?Atelier $atelier): bool
    {
        return $atelier !== null
            && $user->role === User::ROLE_ATELIER
            && $atelier->user_id !== null
            && (string) $user->getKey() === (string) $atelier->user_id;
    }
}
