<?php

namespace App\Modules\Dashboard\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\Auth\Models\User;
use App\Modules\Core\Http\Controllers\Concerns\RendersBackOffice;
use App\Modules\RendezVous\Services\RendezVousService;
use App\Modules\Vetements\Services\VetementService;

class DashboardController extends Controller
{
    use RendersBackOffice;

    public function __construct(
        private AtelierService $ateliers,
        private RendezVousService $rendezVous,
        private VetementService $vetements
    ) {
    }

    public function index()
    {
        $user = auth()->user();
        $atelier = null;
        $statsAtelier = null;

        if ($user instanceof User && $user->role === User::ROLE_ATELIER) {
            try {
                $atelier = $this->ateliers->findOwnedByUser((string) $user->getKey());
                if ($atelier) {
                    $id = (string) $atelier->getKey();
                    $statsAtelier = [
                        'rdv' => $this->rendezVous->countsPourAtelier($id),
                        'pieces' => $this->vetements->countsForAtelier($id),
                    ];
                }
            } catch (\Throwable $e) {
                $statsAtelier = null;
            }
        }

        return $this->backView('back.dashboard', [
            'atelier' => $atelier,
            'statsAtelier' => $statsAtelier,
        ]);
    }
}
