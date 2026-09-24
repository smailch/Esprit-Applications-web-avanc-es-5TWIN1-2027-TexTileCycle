<?php

namespace App\Http\Middleware;

use App\Modules\Auth\Services\RoleNavigationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return redirect(RoleNavigationService::redirectWhenAuthenticated(Auth::guard($guard)->user()));
            }
        }

        return $next($request);
    }
}
