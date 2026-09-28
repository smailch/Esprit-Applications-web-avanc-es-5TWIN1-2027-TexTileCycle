<?php

namespace App\Modules\Dashboard\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;

class DashboardController extends Controller
{
    use RendersBackOffice;

    public function index()
    {
        return $this->backView('back.dashboard');
    }
}
