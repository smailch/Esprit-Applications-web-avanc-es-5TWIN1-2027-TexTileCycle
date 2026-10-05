<?php

namespace App\Modules\Core\Services;

use App\Modules\Auth\Services\RoleNavigationService;

/**
 * Menu sidebar back-office filtré par rôle utilisateur.
 */
class BackMenuService
{
    /**
     * @return list<array{label:string,route:string,icon:string,module:string}>
     */
    public static function items(): array
    {
        return RoleNavigationService::menuFor(auth()->user());
    }
}
