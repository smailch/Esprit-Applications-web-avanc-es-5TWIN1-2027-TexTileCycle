<?php

namespace App\Modules\Statistiques\Models;

use Jenssegers\Mongodb\Eloquent\Model;

/**
 * Métriques d'impact environnemental consolidées par période.
 */
class ImpactEcologique extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'impact_ecologique';

    protected $fillable = [
        'periode',
        'vetements_sauves',
        'co2_evite_kg',
        'eau_economisee_litres',
    ];

    protected $casts = [
        'periode' => 'datetime',
        'vetements_sauves' => 'integer',
        'co2_evite_kg' => 'float',
        'eau_economisee_litres' => 'float',
    ];
}
