<?php

namespace App\Modules\Statistiques\Models;

use Database\Factories\StatistiqueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Jenssegers\Mongodb\Eloquent\Model;

/**
 * Agrégat mensuel consolidé de l'activité de la plateforme.
 */
class Statistique extends Model
{
    use HasFactory;

    public const SOURCE_CONSOLIDATION = 'consolidation';

    public const SOURCE_SEED = 'seed';

    protected $connection = 'mongodb';

    protected $collection = 'statistiques';

    protected $fillable = [
        'periode',
        'nb_vetements',
        'nb_reparations',
        'nb_dons',
        'nb_utilisateurs',
        'nb_ateliers',
        'source',
    ];

    protected $casts = [
        'periode' => 'datetime',
        'nb_vetements' => 'integer',
        'nb_reparations' => 'integer',
        'nb_dons' => 'integer',
        'nb_utilisateurs' => 'integer',
        'nb_ateliers' => 'integer',
    ];

    protected $attributes = [
        'source' => self::SOURCE_CONSOLIDATION,
    ];

    protected static function newFactory()
    {
        return StatistiqueFactory::new();
    }

    /** Impact écologique de la même période (1:1 sur `periode`). */
    public function impact(): HasOne
    {
        return $this->hasOne(ImpactEcologique::class, 'periode', 'periode');
    }

    public function estDemo(): bool
    {
        return $this->source === self::SOURCE_SEED;
    }
}
