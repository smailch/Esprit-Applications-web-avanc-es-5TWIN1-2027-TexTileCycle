<?php

namespace App\Modules\Ateliers\Policies;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Auth\Models\User;

class ServicePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    /**
     * Liste de son propre catalogue (le scope atelier_id est appliqué par ServiceCatalogueService).
     */
    public function viewAny(User $user): bool
    {
        return $user->role === User::ROLE_ATELIER;
    }

    public function view(User $user, Service $service): bool
    {
        return $this->ownsService($user, $service);
    }

    /**
     * Usage : Gate::authorize('create', [Service::class, $atelier]).
     */
    public function create(User $user, ?Atelier $atelier = null): bool
    {
        return AtelierPolicy::owns($user, $atelier);
    }

    public function update(User $user, Service $service): bool
    {
        return $this->ownsService($user, $service);
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->ownsService($user, $service);
    }

    private function ownsService(User $user, Service $service): bool
    {
        if ($user->role !== User::ROLE_ATELIER || $service->atelier_id === null) {
            return false;
        }

        $atelier = $service->atelier;

        return $atelier !== null
            && (string) $atelier->getKey() === (string) $service->atelier_id
            && AtelierPolicy::owns($user, $atelier);
    }
}
