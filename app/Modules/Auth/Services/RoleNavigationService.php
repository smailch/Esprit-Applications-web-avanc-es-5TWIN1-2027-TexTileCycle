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
                'back.vetements.*',
                'back.ateliers',
                'back.ateliers.*',
                'back.rdv',
                'back.rdv.*',
                'back.parametres',
            ],
            User::ROLE_ASSOCIATION => [
                'back.dashboard',
                'back.dons',
                'back.dons.*',
                'back.associations',
                'back.associations.*',
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

        $items = array_values(array_filter(
            self::allMenuItems(),
            fn (array $item) => collect($patterns)->contains(
                fn (string $pattern) => Str::is($pattern, $item['route'])
            )
        ));

        if ($user->role === User::ROLE_ATELIER) {
            return self::relabelMenuForAtelier($items);
        }

        if ($user->role === User::ROLE_ASSOCIATION) {
            return self::relabelMenuForAssociation($items);
        }

        return $items;
    }

    /**
     * Libellés du menu pour un compte atelier (ses pièces et ses rendez-vous).
     *
     * @param  list<array{label:string,route:string,icon:string,module:string}>  $items
     * @return list<array{label:string,route:string,icon:string,module:string}>
     */
    public static function relabelMenuForAtelier(array $items): array
    {
        return array_map(function (array $item) {
            $item['label'] = match ($item['route']) {
                'back.vetements' => 'Pièces à traiter',
                'back.rdv' => 'Mes rendez-vous',
                'back.ateliers' => 'Mon atelier',
                default => $item['label'],
            };

            return $item;
        }, $items);
    }

    public static function relabelMenuForAssociation(array $items): array
    {
        return array_map(function (array $item) {
            $item['label'] = match ($item['route']) {
                'back.associations' => 'Mon association',
                'back.dons' => 'Dons reçus',
                default => $item['label'],
            };

            return $item;
        }, $items);
    }

    /**
     * Compte atelier actif sans fiche : il doit la créer avant le reste du back-office.
     */
    public static function atelierDoitCreerSaFiche(User $user): bool
    {
        if ($user->role !== User::ROLE_ATELIER) {
            return false;
        }

        try {
            return app(\App\Modules\Ateliers\Services\AtelierService::class)
                ->findOwnedByUser((string) $user->getKey()) === null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Compte association actif sans fiche : il doit la créer avant le reste du back-office.
     */
    public static function associationDoitCreerSaFiche(User $user): bool
    {
        if ($user->role !== User::ROLE_ASSOCIATION) {
            return false;
        }

        try {
            return app(\App\Modules\Associations\Services\AssociationService::class)
                ->findOwnedByUser((string) $user->getKey()) === null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function redirectAfterLogin(User $user): string
    {
        if (self::atelierDoitCreerSaFiche($user)) {
            return route('back.ateliers.profil');
        }

        if (self::associationDoitCreerSaFiche($user)) {
            return route('back.associations.create');
        }

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
