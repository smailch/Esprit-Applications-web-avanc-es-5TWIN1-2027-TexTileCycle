<?php

namespace App\Modules\Statistiques\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Calculateur d'impact écologique d'un vêtement détourné de la décharge.
 *
 * Les coefficients sont des ordres de grandeur indicatifs (empreinte de
 * production d'un vêtement neuf, sources type ADEME / Ellen MacArthur).
 * Une réparation ou un don évite l'achat d'un neuf (100 %) ; le recyclage
 * ne récupère qu'une partie de la matière (30 %).
 */
class ImpactCalculator
{
    /** mot-clé du type => [kg CO2, litres d'eau] pour un vêtement neuf */
    public const COEFFICIENTS = [
        'jean' => [23.0, 7500],
        'pantalon' => [17.0, 5000],
        'veste' => [30.0, 8000],
        'manteau' => [35.0, 9000],
        'pull' => [15.0, 5000],
        'robe' => [15.0, 4500],
        'chemise' => [10.0, 3000],
        'jupe' => [10.0, 3000],
        't-shirt' => [7.0, 2700],
        'tshirt' => [7.0, 2700],
        'short' => [8.0, 2500],
    ];

    public const DEFAUT = [12.0, 4000];

    public const FACTEURS = [
        'reparation' => 1.0,
        'don' => 1.0,
        'recyclage' => 0.3,
    ];

    /**
     * @return array{co2: float, eau: float}
     */
    public function pourVetement(?string $type, string $valorisation): array
    {
        [$co2, $eau] = $this->coefficients($type);
        $facteur = self::FACTEURS[$valorisation] ?? 1.0;

        return ['co2' => $co2 * $facteur, 'eau' => $eau * $facteur];
    }

    /**
     * @param  Collection<int, array{type: ?string, kind: string}>  $evenements
     * @return array{vetements: int, co2: float, eau: float}
     */
    public function total(Collection $evenements): array
    {
        return $evenements->reduce(function (array $acc, array $event) {
            $impact = $this->pourVetement($event['type'] ?? null, $event['kind']);

            return [
                'vetements' => $acc['vetements'] + 1,
                'co2' => $acc['co2'] + $impact['co2'],
                'eau' => $acc['eau'] + $impact['eau'],
            ];
        }, ['vetements' => 0, 'co2' => 0.0, 'eau' => 0.0]);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function coefficients(?string $type): array
    {
        $normalise = Str::lower(Str::ascii((string) $type));

        foreach (self::COEFFICIENTS as $motCle => $valeurs) {
            if ($normalise !== '' && str_contains($normalise, $motCle)) {
                return $valeurs;
            }
        }

        return self::DEFAUT;
    }
}
