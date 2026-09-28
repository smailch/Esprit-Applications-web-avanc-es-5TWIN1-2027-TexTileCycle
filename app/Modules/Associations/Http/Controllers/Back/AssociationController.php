<?php

namespace App\Modules\Associations\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;

class AssociationController extends Controller
{
    use RendersBackOffice;

    public function index()
    {
        return $this->backView('back.module', [
            'pageTitle' => 'Associations',
        ]);
    }
}
