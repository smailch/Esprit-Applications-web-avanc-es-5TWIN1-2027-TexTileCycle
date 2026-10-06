<?php

namespace App\Modules\RendezVous\Policies;

use App\Modules\Ateliers\Policies\AtelierPolicy;
use App\Modules\Auth\Models\User;
use App\Modules\RendezVous\Models\RendezVous;

class RendezVousPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    /**
     * Liste de ses propres rendez-vous (le scope atelier_id est appliqué par RendezVousService).
     */
    public function viewAny(User $user): bool
    {
        return $user->role === User::ROLE_ATELIER;
    }

    public function view(User $user, RendezVous $rdv): bool
    {
        return $this->ownsRendezVous($user, $rdv);
    }

    public function changerStatut(User $user, RendezVous $rdv): bool
    {
        return $this->ownsRendezVous($user, $rdv);
    }

    private function ownsRendezVous(User $user, RendezVous $rdv): bool
    {
        if ($user->role !== User::ROLE_ATELIER || $rdv->atelier_id === null) {
            return false;
        }

        $atelier = $rdv->atelier;

        return $atelier !== null
            && (string) $atelier->getKey() === (string) $rdv->atelier_id
            && AtelierPolicy::owns($user, $atelier);
    }
}
