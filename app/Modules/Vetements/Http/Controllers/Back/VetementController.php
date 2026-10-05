<?php

namespace App\Modules\Vetements\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use App\Modules\Vetements\Services\VetementService;

class VetementController extends Controller
{
    use RendersBackOffice;

    public function __construct(
        private VetementService $vetements
    ) {
    }

    public function index()
    {
        $items = $this->vetements->listAllForBackOffice();

        $rows = $items->map(fn ($vetement) => [
            'name' => $vetement->displayName(),
            'status' => $vetement->statusLabel(),
            'tone' => $vetement->statusTone(),
            'date' => $vetement->created_at?->translatedFormat('d M Y') ?? '—',
            'owner' => $vetement->ownerShortName(),
            'path' => $vetement->intendedActionLabel(),
        ])->all();

        return $this->backView('back.module', [
            'pageTitle' => 'Vêtements',
            'rows' => $rows,
        ]);
    }
}
