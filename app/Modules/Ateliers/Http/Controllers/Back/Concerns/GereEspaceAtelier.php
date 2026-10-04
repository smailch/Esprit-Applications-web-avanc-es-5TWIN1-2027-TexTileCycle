<?php

namespace App\Modules\Ateliers\Http\Controllers\Back\Concerns;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Auth\Models\User;
use Closure;
use Illuminate\Http\Request;

/**
 * Espace du rôle atelier : l'atelier est toujours celui du compte connecté
 * (AtelierService::findOwnedByUser), jamais un identifiant venu de la requête.
 * Requiert une propriété $ateliers (AtelierService) et le trait RendersBackOffice.
 */
trait GereEspaceAtelier
{
    /**
     * À appeler dans le constructeur : l'admin a sa propre gestion, les autres rôles n'ont rien à faire ici.
     */
    protected function reserverAuRoleAtelier(): void
    {
        $this->middleware(function (Request $request, Closure $next) {
            abort_unless(
                $request->user()?->role === User::ROLE_ATELIER,
                403,
                'Cet espace est réservé aux comptes atelier.'
            );

            return $next($request);
        });
    }

    protected function monAtelier(Request $request): ?Atelier
    {
        return $this->ateliers->findOwnedByUser((string) $request->user()->getKey());
    }

    protected function vueAtelierNonConfigure()
    {
        return $this->backView('back.ateliers.espace.vide', ['pageTitle' => 'Mon atelier']);
    }

    protected function redirectionAtelierNonConfigure()
    {
        return redirect()
            ->route('back.ateliers.profil')
            ->with('error', "Votre atelier n'est pas encore configuré. Contactez l'administrateur.");
    }
}
