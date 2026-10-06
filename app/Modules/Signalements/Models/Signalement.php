<?php

namespace App\Modules\Signalements\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Core\Support\MongoCollections;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jenssegers\Mongodb\Eloquent\Model;

class Signalement extends Model
{
    public const TYPE_ANNONCE = 'annonce';

    public const TYPE_UTILISATEUR = 'utilisateur';

    public const TYPE_CONTENU = 'contenu';

    public const TYPES = [
        self::TYPE_ANNONCE,
        self::TYPE_UTILISATEUR,
        self::TYPE_CONTENU,
    ];

    public const STATUT_EN_ATTENTE = 'en_attente';

    public const STATUT_TRAITE = 'traite';

    public const STATUT_REJETE = 'rejete';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_TRAITE,
        self::STATUT_REJETE,
    ];

    /**
     * Entités signalables : alias => [collection MongoDB, champ libellé, type par défaut].
     * Indépendant des modèles des autres modules (dont certains ne sont pas encore livrés).
     */
    public const CIBLES = [
        'Vetement' => ['collection' => 'vetements', 'label' => 'type', 'type' => self::TYPE_ANNONCE],
        'Atelier' => ['collection' => 'ateliers', 'label' => 'nom', 'type' => self::TYPE_CONTENU],
        'Association' => ['collection' => 'associations', 'label' => 'nom', 'type' => self::TYPE_CONTENU],
        'Don' => ['collection' => 'dons', 'label' => 'message', 'type' => self::TYPE_ANNONCE],
        'User' => ['collection' => 'users', 'label' => 'name', 'type' => self::TYPE_UTILISATEUR],
    ];

    protected $connection = 'mongodb';

    protected $collection = 'signalements';

    protected $fillable = [
        'user_id',
        'type',
        'cible_id',
        'cible_type',
        'motif',
        'statut',
        'note_admin',
        'traite_par',
        'traite_le',
    ];

    protected $casts = [
        'traite_le' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'statut' => self::STATUT_EN_ATTENTE,
    ];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relation polymorphe vers l'entité signalée (résolue via la morph map
     * déclarée dans SignalementsModuleServiceProvider).
     */
    public function cible()
    {
        return $this->morphTo('cible', 'cible_type', 'cible_id');
    }

    /**
     * Document brut de la cible, lu directement dans sa collection.
     */
    public function cibleDocument(): ?array
    {
        $config = self::CIBLES[$this->cible_type] ?? null;

        return $config ? MongoCollections::findById($config['collection'], $this->cible_id) : null;
    }

    public function cibleLabel(): string
    {
        $doc = $this->cibleDocument();
        $field = self::CIBLES[$this->cible_type]['label'] ?? null;

        if (! $doc) {
            return $this->cible_type.' supprimé(e)';
        }

        return (string) ($doc[$field] ?? $this->cible_type.' #'.substr((string) $this->cible_id, -6));
    }

    public const STATUT_LABELS = [
        self::STATUT_EN_ATTENTE => 'En attente',
        self::STATUT_TRAITE => 'Traité',
        self::STATUT_REJETE => 'Rejeté',
    ];

    public function statutLabel(): string
    {
        return self::STATUT_LABELS[$this->statut] ?? self::STATUT_LABELS[self::STATUT_EN_ATTENTE];
    }

    public function statutTone(): string
    {
        return match ($this->statut) {
            self::STATUT_TRAITE => 'green',
            self::STATUT_REJETE => 'purple',
            default => 'orange',
        };
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_UTILISATEUR => 'Utilisateur',
            self::TYPE_CONTENU => 'Contenu',
            default => 'Annonce',
        };
    }

    public function scopeEnAttente($query)
    {
        return $query->where('statut', self::STATUT_EN_ATTENTE);
    }
}
