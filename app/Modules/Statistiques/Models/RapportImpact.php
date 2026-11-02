<?php

namespace App\Modules\Statistiques\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jenssegers\Mongodb\Eloquent\Model;

/**
 * Rapport d'impact mensuel rédigé par l'IA (un par mois, régénérable).
 * `faits` : chiffres calculés par la plateforme ; `contenu` : texte rédigé à partir de ces seuls chiffres.
 * Pas de cast « array » : MongoDB stocke les sous-documents nativement.
 */
class RapportImpact extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'rapports_impact';

    protected $fillable = [
        'mois',
        'faits',
        'contenu',
        'source',
        'chiffres_rejetes',
        'genere_par',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'genere_par', '_id');
    }

    public function estRedigeParIa(): bool
    {
        return $this->source === 'gemini';
    }
}
