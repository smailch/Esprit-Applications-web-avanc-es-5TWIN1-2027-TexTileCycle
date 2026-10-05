<?php

namespace App\Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages réservées aux citoyens connectés (parcours front).
 */
class EnsureFrontCitizenArea
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()
                ->guest(route('front.login'))
                ->with('info', 'Connectez-vous pour accéder à votre espace citoyen.');
        }

        return $next($request);
    }
}
