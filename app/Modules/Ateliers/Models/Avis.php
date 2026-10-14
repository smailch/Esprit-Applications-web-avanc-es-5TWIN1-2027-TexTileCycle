<?php

namespace App\Modules\Ateliers\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Jenssegers\Mongodb\Eloquent\Model;

/**
 * Avis d'un client sur un atelier (collection "avis") : un seul par client et par atelier, modifiable.
 * Réservé aux clients qui ont un rendez-vous terminé chez cet atelier (voir AvisService::peutDonnerAvis).
 */
class Avis extends Model
{
    public const NOTE_MIN = 1;

    public const NOTE_MAX = 5;

    public const COMMENTAIRE_MAX = 500;

    /** Libellé de chaque note, affiché à côté des étoiles. */
    public const LIBELLES = [
        1 => 'Décevant',
        2 => 'Moyen',
        3 => 'Correct',
        4 => 'Très bien',
        5 => 'Excellent',
    ];

    protected $connection = 'mongodb';

    protected $collection = 'avis';

    protected $fillable = [
        'atelier_id',
        'user_id',
        'rendez_vous_id',
        'note',
        'commentaire',
    ];

    protected $casts = [
        'note' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    public function libelle(): string
    {
        return self::LIBELLES[(int) $this->note] ?? '';
    }

    /** « Prénom N. » : le nom complet du client n'est jamais affiché publiquement. */
    public function auteurAffiche(): string
    {
        $mots = preg_split('/\s+/u', trim((string) $this->auteur?->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($mots === []) {
            return 'Client';
        }

        $prenom = Str::ucfirst(Str::lower($mots[0]));

        return isset($mots[1]) ? $prenom.' '.Str::upper(Str::substr($mots[1], 0, 1)).'.' : $prenom;
    }

    public function initiale(): string
    {
        return Str::upper(Str::substr($this->auteurAffiche(), 0, 1));
    }

    /** « il y a 3 jours », en français quelle que soit la langue de l'application. */
    public function dateRelative(): string
    {
        return $this->created_at ? $this->created_at->locale('fr')->diffForHumans() : '';
    }

    public function estModifie(): bool
    {
        return $this->created_at && $this->updated_at && $this->updated_at->gt($this->created_at->copy()->addMinute());
    }
}
