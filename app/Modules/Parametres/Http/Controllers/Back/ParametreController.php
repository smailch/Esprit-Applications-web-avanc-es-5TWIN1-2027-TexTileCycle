<?php

namespace App\Modules\Parametres\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;

class ParametreController extends Controller
{
    use RendersBackOffice;

    public function index()
    {
        return $this->backView('back.module', [
            'pageTitle' => 'Paramètres',
        ]);
    }
}
