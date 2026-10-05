<?php

namespace Database\Factories;

use App\Modules\Ateliers\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Usage : ServiceFactory::new()->state(['atelier_id' => $id])->create().
 * Sans atelier_id, un atelier (et son compte) est créé en base, même avec make().
 *
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    private const NOMS = [
        'Recoudre une déchirure',
        'Changer une fermeture',
        'Raccourcir un ourlet',
        'Retouche denim',
        'Upcycling créatif',
        'Reprise de tricot',
        'Ajustement de robe',
        'Remplacement de doublure',
    ];

    public function definition(): array
    {
        return [
            'atelier_id' => fn () => (string) AtelierFactory::new()->create()->getKey(),
            'nom' => $this->faker->randomElement(self::NOMS),
            'description' => $this->faker->sentence(8),
            'prix_estime' => $this->faker->numberBetween(5, 80),
            'duree_estimee' => $this->faker->randomElement([15, 20, 30, 45, 60, 90, 120, 150, 180]),
        ];
    }
}
