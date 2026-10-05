<?php

namespace App\Modules\Auth\Http\Middleware;

use App\Modules\Auth\Services\RoleNavigationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBackOfficeRoute
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $routeName = $request->route()?->getName();

        if (! RoleNavigationService::canAccessRoute($user, $routeName)) {
            abort(403, 'Vous n\'avez pas accès à cette section du back-office.');
        }

        return $next($request);
    }
}
