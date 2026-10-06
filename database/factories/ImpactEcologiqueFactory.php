<?php

namespace Database\Factories;

use App\Modules\Statistiques\Models\ImpactEcologique;
use App\Modules\Statistiques\Models\Statistique;
use App\Modules\Statistiques\Services\ImpactCalculator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImpactEcologique>
 */
class ImpactEcologiqueFactory extends Factory
{
    protected $model = ImpactEcologique::class;

    public function definition(): array
    {
        $sauves = $this->faker->numberBetween(10, 60);

        return $this->impactPour($sauves) + [
            'periode' => now()->startOfMonth()->subMonths($this->faker->numberBetween(1, 24)),
            'source' => Statistique::SOURCE_SEED,
        ];
    }

    /**
     * Impact cohérent avec une statistique : vêtements sauvés = réparations + dons.
     */
    public function pourStatistique(Statistique $statistique): static
    {
        return $this->state(fn () => $this->impactPour($statistique->nb_reparations + $statistique->nb_dons) + [
            'periode' => $statistique->periode,
            'source' => $statistique->source,
        ]);
    }

    private function impactPour(int $sauves): array
    {
        [$co2, $eau] = ImpactCalculator::DEFAUT;
        $variation = $this->faker->randomFloat(2, 0.85, 1.15);

        return [
            'vetements_sauves' => $sauves,
            'co2_evite_kg' => round($sauves * $co2 * $variation, 1),
            'eau_economisee_litres' => round($sauves * $eau * $variation),
        ];
    }
}
