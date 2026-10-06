<?php

namespace Database\Factories;

use App\Modules\Statistiques\Models\ImpactEcologique;
use App\Modules\Statistiques\Models\Statistique;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Usage : Statistique::factory()->periode(Carbon::parse('2025-04-01'))->create();
 * Chaque statistique créée reçoit son ImpactEcologique de la même période (relation 1:1).
 *
 * @extends Factory<Statistique>
 */
class StatistiqueFactory extends Factory
{
    protected $model = Statistique::class;

    public function definition(): array
    {
        $reparations = $this->faker->numberBetween(10, 40);
        $dons = $this->faker->numberBetween(5, 25);

        return [
            'periode' => now()->startOfMonth()->subMonths($this->faker->numberBetween(1, 24)),
            'nb_vetements' => $reparations + $dons + $this->faker->numberBetween(5, 20),
            'nb_reparations' => $reparations,
            'nb_dons' => $dons,
            'nb_utilisateurs' => $this->faker->numberBetween(5, 40),
            'nb_ateliers' => $this->faker->numberBetween(3, 12),
            'source' => Statistique::SOURCE_SEED,
        ];
    }

    public function periode(Carbon $mois): static
    {
        return $this->state(['periode' => $mois->copy()->startOfMonth()]);
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Statistique $statistique) {
            ImpactEcologique::factory()
                ->pourStatistique($statistique)
                ->create();
        });
    }
}
