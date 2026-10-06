<?php

namespace App\Modules\Ateliers\Http\Middleware;

use App\Modules\Auth\Models\User;
use App\Modules\Auth\Services\RoleNavigationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un compte atelier validé doit créer sa fiche avant d'utiliser le reste du back-office.
 */
class EnsureAtelierHasCreatedFiche
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! RoleNavigationService::atelierDoitCreerSaFiche($user)) {
            return $next($request);
        }

        $route = $request->route()?->getName();

        if (in_array($route, [
            'back.ateliers.profil',
            'back.ateliers.profil.store',
            'back.ateliers.profil.update',
        ], true)) {
            return $next($request);
        }

        return redirect()
            ->route('back.ateliers.profil')
            ->with('info', 'Créez d\'abord la fiche de votre atelier pour continuer.');
    }
}
