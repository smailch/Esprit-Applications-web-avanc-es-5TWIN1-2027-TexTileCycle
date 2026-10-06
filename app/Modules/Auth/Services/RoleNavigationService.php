<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\User;
use Illuminate\Support\Str;

class RoleNavigationService
{
    /**
     * @return list<array{label:string,route:string,icon:string,module:string}>
     */
    public static function allMenuItems(): array
    {
        return [
            ['label' => 'Tableau de bord', 'route' => 'back.dashboard', 'icon' => 'layout-dashboard', 'module' => 'Dashboard'],
            ['label' => 'Vêtements', 'route' => 'back.vetements', 'icon' => 'shirt', 'module' => 'Vetements'],
            ['label' => 'Ateliers & services', 'route' => 'back.ateliers', 'icon' => 'wrench', 'module' => 'Ateliers'],
            ['label' => 'Rendez-vous', 'route' => 'back.rdv', 'icon' => 'calendar-days', 'module' => 'RendezVous'],
            ['label' => 'Dons', 'route' => 'back.dons', 'icon' => 'gift', 'module' => 'Dons'],
            ['label' => 'Associations', 'route' => 'back.associations', 'icon' => 'heart-handshake', 'module' => 'Associations'],
            ['label' => 'Partenaires', 'route' => 'back.partenaires.index', 'icon' => 'badge-check', 'module' => 'Partenaires'],
            ['label' => 'Signalements', 'route' => 'back.signalements.index', 'icon' => 'circle-alert', 'module' => 'Signalements'],
            ['label' => 'Statistiques & impact', 'route' => 'back.statistiques.index', 'icon' => 'bar-chart-3', 'module' => 'Statistiques'],
            ['label' => 'Utilisateurs', 'route' => 'back.users.index', 'icon' => 'users', 'module' => 'Auth'],
        ];
    }

    public static function canAccessBackOffice(?User $user): bool
    {
        return $user !== null && $user->canAccessBackOffice();
    }

    /**
     * @return list<string> patterns Laravel Str::is()
     */
    public static function allowedRoutePatterns(User $user): array
    {
        if ($user->isAdmin()) {
            return ['back.*'];
        }

        return match ($user->role) {
            User::ROLE_ATELIER => [
                'back.dashboard',
                'back.vetements',
                'back.ateliers',
                'back.ateliers.*',
                'back.rdv',
                'back.rdv.*',
                'back.parametres',
            ],
            User::ROLE_ASSOCIATION => [
                'back.dashboard',
                'back.dons',
                'back.associations',
                'back.parametres',
            ],
            default => [],
        };
    }

    public static function canAccessRoute(?User $user, ?string $routeName): bool
    {
        if (! $user || ! $routeName) {
            return false;
        }

        if (! $user->canAccessBackOffice()) {
            return false;
        }

        foreach (self::allowedRoutePatterns($user) as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{label:string,route:string,icon:string,module:string}>
     */
    public static function menuFor(?User $user): array
    {
        if (! self::canAccessBackOffice($user)) {
            return [];
        }

        $patterns = self::allowedRoutePatterns($user);

        return array_values(array_filter(
            self::allMenuItems(),
            fn (array $item) => collect($patterns)->contains(
                fn (string $pattern) => Str::is($pattern, $item['route'])
            )
        ));
    }

    public static function redirectAfterLogin(User $user): string
    {
        if ($user->canAccessBackOffice()) {
            return route('back.dashboard');
        }

        return route('front.vetements');
    }

    public static function redirectWhenAuthenticated(User $user): string
    {
        return self::redirectAfterLogin($user);
    }
}
