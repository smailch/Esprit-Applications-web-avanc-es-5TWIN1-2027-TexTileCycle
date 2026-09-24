<?php

namespace App\Modules\Core\Services;

/**
 * Menu sidebar back-office partagé entre tous les modules admin.
 */
class BackMenuService
{
    public static function items(): array
    {
        return [
            ['label' => 'Tableau de bord', 'route' => 'back.dashboard', 'icon' => 'layout-dashboard', 'module' => 'Dashboard'],
            ['label' => 'Vêtements', 'route' => 'back.vetements', 'icon' => 'shirt', 'module' => 'Vetements'],
            ['label' => 'Ateliers & services', 'route' => 'back.ateliers', 'icon' => 'wrench', 'module' => 'Ateliers'],
            ['label' => 'Rendez-vous', 'route' => 'back.rdv', 'icon' => 'calendar-days', 'module' => 'RendezVous'],
            ['label' => 'Dons', 'route' => 'back.dons', 'icon' => 'gift', 'module' => 'Dons'],
            ['label' => 'Associations', 'route' => 'back.associations', 'icon' => 'heart-handshake', 'module' => 'Associations'],
            ['label' => 'Signalements', 'route' => 'back.signalements', 'icon' => 'circle-alert', 'module' => 'Signalements'],
            ['label' => 'Statistiques & impact', 'route' => 'back.statistiques', 'icon' => 'bar-chart-3', 'module' => 'Statistiques'],
        ];
    }
}
