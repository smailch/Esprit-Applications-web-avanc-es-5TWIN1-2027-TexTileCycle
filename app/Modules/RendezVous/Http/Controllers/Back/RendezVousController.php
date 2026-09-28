<?php

namespace App\Modules\RendezVous\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;

class RendezVousController extends Controller
{
    use RendersBackOffice;

    public function index()
    {
        return $this->backView('back.module', [
            'pageTitle' => 'Rendez-vous',
        ]);
    }
}
