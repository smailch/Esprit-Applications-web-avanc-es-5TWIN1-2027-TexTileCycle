<?php

namespace App\Modules\Vetements\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;

class VetementController extends Controller
{
    use RendersBackOffice;

    public function index()
    {
        $rows = [
            ['name' => 'Veste en jean', 'status' => 'En attente', 'tone' => 'orange', 'date' => '12 sept. 2026', 'owner' => 'Yasmine B.'],
            ['name' => 'Pull en laine', 'status' => 'En réparation', 'tone' => 'blue', 'date' => '08 sept. 2026', 'owner' => 'Sami K.'],
            ['name' => 'Pantalon chino', 'status' => 'Donné', 'tone' => 'purple', 'date' => '02 sept. 2026', 'owner' => 'Amel M.'],
            ['name' => 'Robe fleurie', 'status' => 'Réparé', 'tone' => 'green', 'date' => '28 août 2026', 'owner' => 'Nour A.'],
        ];

        return $this->backView('back.module', [
            'pageTitle' => 'Vêtements',
            'rows' => $rows,
        ]);
    }
}
