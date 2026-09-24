<?php

namespace App\Modules\Dons\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;

class DonController extends Controller
{
    use RendersBackOffice;

    public function index()
    {
        return $this->backView('back.module', [
            'pageTitle' => 'Dons',
        ]);
    }
}
