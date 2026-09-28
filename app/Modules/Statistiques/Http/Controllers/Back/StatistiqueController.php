<?php

namespace App\Modules\Statistiques\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;

class StatistiqueController extends Controller
{
    use RendersBackOffice;

    public function index()
    {
        return $this->backView('back.statistiques');
    }
}
