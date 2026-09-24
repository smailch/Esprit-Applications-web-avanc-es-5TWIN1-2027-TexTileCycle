<?php

namespace App\Modules\Auth\Http\Middleware;

use App\Modules\Auth\Services\RoleNavigationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBackOfficeAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()
                ->guest(route('front.login'))
                ->with('info', 'Connectez-vous avec un compte professionnel pour accéder au back-office.');
        }

        if (! RoleNavigationService::canAccessBackOffice($user)) {
            return redirect()
                ->route('front.home')
                ->withErrors(['access' => 'Le back-office est réservé aux comptes atelier, association ou administrateur.']);
        }

        return $next($request);
    }
}
