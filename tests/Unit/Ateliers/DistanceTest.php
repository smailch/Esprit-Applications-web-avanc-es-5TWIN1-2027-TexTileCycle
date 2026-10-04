<?php

namespace Tests\Unit\Ateliers;

use App\Modules\Ateliers\Services\AtelierService;
use PHPUnit\Framework\TestCase;

class DistanceTest extends TestCase
{
    public function test_meme_point_donne_zero(): void
    {
        $this->assertSame(0.0, AtelierService::distanceKm(36.8782, 10.3247, 36.8782, 10.3247));
    }

    public function test_un_degre_de_longitude_a_l_equateur(): void
    {
        $this->assertEqualsWithDelta(111.195, AtelierService::distanceKm(0, 0, 0, 1), 0.01);
    }

    public function test_paris_londres(): void
    {
        $this->assertEqualsWithDelta(343.5, AtelierService::distanceKm(48.8566, 2.3522, 51.5074, -0.1278), 1.0);
    }

    public function test_distance_symetrique(): void
    {
        $aller = AtelierService::distanceKm(36.8782, 10.3247, 36.8092, 10.1406);
        $retour = AtelierService::distanceKm(36.8092, 10.1406, 36.8782, 10.3247);

        $this->assertEqualsWithDelta($aller, $retour, 1e-9);
        $this->assertEqualsWithDelta(18.0, $aller, 1.0);
    }

    public function test_points_antipodes_sans_erreur_numerique(): void
    {
        $this->assertEqualsWithDelta(M_PI * AtelierService::RAYON_TERRE_KM, AtelierService::distanceKm(0, 0, 0, 180), 0.01);
    }

    public function test_format_distance(): void
    {
        $this->assertSame('1,2 km', AtelierService::formatDistance(1.234));
        $this->assertSame('0,0 km', AtelierService::formatDistance(0.0));
        $this->assertNull(AtelierService::formatDistance(null));
    }
}
