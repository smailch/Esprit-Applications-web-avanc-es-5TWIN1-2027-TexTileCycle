<?php

namespace Tests\Unit\Ateliers;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use Database\Factories\AtelierFactory;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Collection;
use Tests\Concerns\BlocksRemoteMongo;
use Tests\TestCase;

class AtelierModelTest extends TestCase
{
    use BlocksRemoteMongo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blockRemoteMongo();
    }

    private function avecServices(Atelier $atelier, string ...$noms): Atelier
    {
        return $atelier->setRelation('services', new Collection(array_map(fn ($nom) => new Service(['nom' => $nom]), $noms)));
    }

    public function test_specialite_affichee_utilise_le_champ_s_il_est_rempli(): void
    {
        $atelier = $this->avecServices(new Atelier(['specialite' => 'Denim & retouches']), 'Ourlet', 'Fermeture');

        $this->assertSame('Denim & retouches', $atelier->specialiteAffichee());
    }

    public function test_specialite_affichee_retombe_sur_les_deux_premiers_services(): void
    {
        $atelier = $this->avecServices(new Atelier(['specialite' => '   ']), 'Ourlet', 'Fermeture', 'Doublure');

        $this->assertSame('Ourlet & Fermeture', $atelier->specialiteAffichee());
    }

    public function test_specialite_affichee_vide_sans_champ_ni_service(): void
    {
        $this->assertSame('', $this->avecServices(new Atelier())->specialiteAffichee());
    }

    public function test_location_calculee_depuis_latitude_longitude(): void
    {
        $atelier = new Atelier(['latitude' => '36.8782', 'longitude' => '10.3247']);
        $atelier->syncLocation();

        $this->assertSame(['type' => 'Point', 'coordinates' => [10.3247, 36.8782]], $atelier->location);
    }

    public function test_distance_km_n_est_jamais_persistee(): void
    {
        $atelier = new Atelier(['nom' => 'X', 'latitude' => 36.8, 'longitude' => 10.2]);
        $atelier->setAttribute('distance_km', 1.5);

        Atelier::getEventDispatcher()->until('eloquent.saving: '.Atelier::class, $atelier);

        $this->assertArrayNotHasKey('distance_km', $atelier->getAttributes());
        $this->assertNotNull($atelier->location);
    }

    public function test_factories_accessibles_via_has_factory(): void
    {
        $this->assertInstanceOf(AtelierFactory::class, Atelier::factory());
        $this->assertInstanceOf(ServiceFactory::class, Service::factory());

        $atelier = Atelier::factory()->make(['user_id' => 'user-1']);
        $service = Service::factory()->make(['atelier_id' => 'atelier-1']);

        $this->assertInstanceOf(Atelier::class, $atelier);
        $this->assertNotEmpty($atelier->specialite);
        $this->assertSame(Atelier::JOURS, array_keys($atelier->horaires));
        $this->assertInstanceOf(Service::class, $service);
        $this->assertSame('atelier-1', $service->atelier_id);
    }
}
