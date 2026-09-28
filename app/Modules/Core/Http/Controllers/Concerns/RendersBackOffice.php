<?php

namespace App\Modules\Core\Http\Controllers\Concerns;

use App\Modules\Core\Services\BackMenuService;

trait RendersBackOffice
{
    protected function backView(string $view, array $data = [])
    {
        return view($view, array_merge(['menuItems' => BackMenuService::items()], $data));
    }
}
