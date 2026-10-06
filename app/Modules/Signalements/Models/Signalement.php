<?php

namespace App\Modules\Signalements\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Core\Support\MongoCollections;
use Database\Factories\SignalementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Jenssegers\Mongodb\Eloquent\Model;

class Signalement extends Model
{
    use HasFactory;

    public const TYPE_ANNONCE = 'annonce';

    public const TYPE_UTILISATEUR = 'utilisateur';

    public const TYPE_CONTENU = 'contenu';

    public const TYPES = [
        self::TYPE_ANNONCE,
        self::TYPE_UTILISATEUR,
        self::TYPE_CONTENU,
    ];

    public const TYPE_LABELS = [
        self::TYPE_ANNONCE => 'Annonce',
        self::TYPE_UTILISATEUR => 'Utilisateur',
        self::TYPE_CONTENU => 'Contenu',
    ];

    public const STATUT_EN_ATTENTE = 'en_attente';

    public const STATUT_TRAITE = 'traite';

    public const STATUT_REJETE = 'rejete';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_TRAITE,
        self::STATUT_REJETE,
    ];

    public const STATUT_LABELS = [
        self::STATUT_EN_ATTENTE => 'En attente',
        self::STATUT_TRAITE => 'Traité',
        self::STATUT_REJETE => 'Rejeté',
    ];

    /**
     * Entités signalables : alias morph => [collection MongoDB, champ libellé, type par défaut, libellé].
     * Les classes Eloquent correspondantes sont enregistrées dans la morph map
     * (SignalementsModuleServiceProvider).
     */
    public const CIBLES = [
        'Vetement' => ['collection' => 'vetements', 'label' => 'type', 'type' => self::TYPE_ANNONCE, 'nom' => 'Vêtement'],
        'Atelier' => ['collection' => 'ateliers', 'label' => 'nom', 'type' => self::TYPE_CONTENU, 'nom' => 'Atelier'],
        'Association' => ['collection' => 'associations', 'label' => 'nom', 'type' => self::TYPE_CONTENU, 'nom' => 'Association'],
        'Don' => ['collection' => 'dons', 'label' => 'message', 'type' => self::TYPE_ANNONCE, 'nom' => 'Don'],
        'User' => ['collection' => 'users', 'label' => 'name', 'type' => self::TYPE_UTILISATEUR, 'nom' => 'Utilisateur'],
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
        'source',
    ];

    protected $casts = [
        'traite_le' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'statut' => self::STATUT_EN_ATTENTE,
    ];

    protected static function newFactory()
    {
        return SignalementFactory::new();
    }

    /** Citoyen auteur du signalement (N:1 users). */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    /** Administrateur ayant traité le signalement (N:1 users). */
    public function traitePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traite_par', '_id');
    }

    /** Entité signalée : relation polymorphe (Vetement, Atelier, Association, Don, User). */
    public function cible(): MorphTo
    {
        return $this->morphTo('cible', 'cible_type', 'cible_id', '_id');
    }

    /**
     * Autres signalements visant la même entité.
     */
    public function signalementsMemeCible()
    {
        return static::query()
            ->where('cible_type', $this->cible_type)
            ->where('cible_id', $this->cible_id)
            ->where('_id', '!=', $this->getKey())
            ->with('auteur')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function cibleLabel(): string
    {
        $field = self::CIBLES[$this->cible_type]['label'] ?? null;
        $cible = $this->cibleExiste() ? $this->cible : null;

        if (! $cible) {
            $doc = $this->cibleDocument();

            return $doc
                ? (string) ($doc[$field] ?? $this->cibleNom().' #'.substr((string) $this->cible_id, -6))
                : $this->cibleNom().' supprimé(e)';
        }

        return (string) ($cible->{$field} ?: $this->cibleNom().' #'.substr((string) $this->cible_id, -6));
    }

    public function cibleNom(): string
    {
        return self::CIBLES[$this->cible_type]['nom'] ?? (string) $this->cible_type;
    }

    /**
     * Document brut de la cible (repli si le modèle du module n'est pas disponible).
     */
    public function cibleDocument(): ?array
    {
        $config = self::CIBLES[$this->cible_type] ?? null;

        return $config ? MongoCollections::findById($config['collection'], $this->cible_id) : null;
    }

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
        return self::TYPE_LABELS[$this->type] ?? self::TYPE_LABELS[self::TYPE_ANNONCE];
    }

    public function estEnAttente(): bool
    {
        return $this->statut === self::STATUT_EN_ATTENTE;
    }

    public function scopeEnAttente($query)
    {
        return $query->where('statut', self::STATUT_EN_ATTENTE);
    }

    private function cibleExiste(): bool
    {
        return Relation::getMorphedModel((string) $this->cible_type) !== null;
    }
}
