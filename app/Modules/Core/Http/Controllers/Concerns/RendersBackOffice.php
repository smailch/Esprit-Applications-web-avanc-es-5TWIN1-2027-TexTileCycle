<?php

namespace App\Modules\Core\Http\Controllers\Concerns;

use App\Modules\Auth\Models\User;
use App\Modules\Core\Services\BackMenuService;

trait RendersBackOffice
{
    protected function backView(string $view, array $data = [])
    {
        /** @var User|null $user */
        $user = auth()->user();

        return view($view, array_merge([
            'menuItems' => BackMenuService::items(),
            'backUser' => $user,
        ], $data));
    }
}
