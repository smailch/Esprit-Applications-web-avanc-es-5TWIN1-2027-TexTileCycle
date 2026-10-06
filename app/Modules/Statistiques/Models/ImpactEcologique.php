<?php

namespace App\Modules\Statistiques\Models;

use Database\Factories\ImpactEcologiqueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jenssegers\Mongodb\Eloquent\Model;

/**
 * Métriques d'impact environnemental consolidées par période.
 */
class ImpactEcologique extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'impact_ecologique';

    protected $fillable = [
        'periode',
        'vetements_sauves',
        'co2_evite_kg',
        'eau_economisee_litres',
        'source',
    ];

    protected $casts = [
        'periode' => 'datetime',
        'vetements_sauves' => 'integer',
        'co2_evite_kg' => 'float',
        'eau_economisee_litres' => 'float',
    ];

    protected $attributes = [
        'source' => Statistique::SOURCE_CONSOLIDATION,
    ];

    protected static function newFactory()
    {
        return ImpactEcologiqueFactory::new();
    }

    /** Statistiques d'activité de la même période. */
    public function statistique(): BelongsTo
    {
        return $this->belongsTo(Statistique::class, 'periode', 'periode');
    }
}
