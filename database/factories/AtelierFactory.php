<?php

namespace Database\Factories;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Usage : AtelierFactory::new()->actif()->create().
 * Sans state(['user_id' => ...]), un compte atelier est créé en base, même avec make().
 *
 * @extends Factory<Atelier>
 */
class AtelierFactory extends Factory
{
    protected $model = Atelier::class;

    private const VILLES = [
        'Tunis', 'La Marsa', 'Carthage', 'La Goulette', 'Ariana',
        'El Menzah', 'Le Bardo', 'Ben Arous', 'Manouba', 'Le Kram',
    ];

    private const SPECIALITES = [
        'Denim & retouches', 'Upcycling créatif', 'Tricot & textile',
        'Robes & cérémonie', 'Retouches express', 'Couture traditionnelle',
    ];

    public function definition(): array
    {
        $journee = [['09:00', '12:00'], ['14:00', '18:00']];

        return [
            'user_id' => fn () => (string) User::factory()->create(['role' => User::ROLE_ATELIER])->getKey(),
            'nom' => 'Atelier '.$this->faker->unique()->lastName(),
            'specialite' => $this->faker->randomElement(self::SPECIALITES),
            'description' => $this->faker->sentence(12),
            'adresse' => $this->faker->buildingNumber().' Rue '.$this->faker->lastName(),
            'ville' => $this->faker->randomElement(self::VILLES),
            'latitude' => $this->faker->randomFloat(5, 36.74, 36.89),
            'longitude' => $this->faker->randomFloat(5, 10.08, 10.33),
            'telephone' => '+216 '.$this->faker->numerify('7# ### ###'),
            'horaires' => [
                'lundi' => $journee,
                'mardi' => $journee,
                'mercredi' => $journee,
                'jeudi' => $journee,
                'vendredi' => $journee,
                'samedi' => [['09:00', '13:00']],
                'dimanche' => [],
            ],
            'note_moyenne' => $this->faker->randomFloat(1, 3.8, 4.9),
            'nb_avis' => $this->faker->numberBetween(5, 60),
            'statut' => Atelier::STATUT_ACTIF,
        ];
    }

    public function actif(): static
    {
        return $this->state(['statut' => Atelier::STATUT_ACTIF]);
    }

    public function enAttente(): static
    {
        return $this->state(['statut' => Atelier::STATUT_EN_ATTENTE]);
    }

    public function suspendu(): static
    {
        return $this->state(['statut' => Atelier::STATUT_SUSPENDU]);
    }
}
