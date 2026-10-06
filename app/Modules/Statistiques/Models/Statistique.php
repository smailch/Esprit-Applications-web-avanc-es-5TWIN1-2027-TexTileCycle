<?php

namespace App\Modules\Statistiques\Models;

use Jenssegers\Mongodb\Eloquent\Model;

/**
 * Agrégat mensuel consolidé de l'activité de la plateforme.
 */
class Statistique extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'statistiques';

    protected $fillable = [
        'periode',
        'nb_vetements',
        'nb_reparations',
        'nb_dons',
        'nb_utilisateurs',
        'nb_ateliers',
    ];

    protected $casts = [
        'periode' => 'datetime',
        'nb_vetements' => 'integer',
        'nb_reparations' => 'integer',
        'nb_dons' => 'integer',
        'nb_utilisateurs' => 'integer',
        'nb_ateliers' => 'integer',
    ];
}
