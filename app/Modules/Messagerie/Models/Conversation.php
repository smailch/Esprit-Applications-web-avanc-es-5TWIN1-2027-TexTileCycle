<?php

namespace App\Modules\Messagerie\Models;

use App\Modules\Associations\Models\Association;
use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Auth\Models\User;
use App\Modules\Signalements\Models\Signalement;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Jenssegers\Mongodb\Eloquent\Model;

/**
 * Conversation entre l'équipe d'administration et un utilisateur
 * (citoyen ou responsable d'un atelier / d'une association).
 * La boîte est partagée : tous les administrateurs voient toutes les conversations.
 */
class Conversation extends Model
{
    use HasFactory;

    public const STATUT_OUVERTE = 'ouverte';

    public const STATUT_FERMEE = 'fermee';

    public const STATUTS = [
        self::STATUT_OUVERTE => 'Ouverte',
        self::STATUT_FERMEE => 'Fermée',
    ];

    /** Côté d'un participant : l'équipe admin ou l'utilisateur. */
    public const COTE_ADMIN = 'admin';

    public const COTE_UTILISATEUR = 'utilisateur';

    /** Contextes auxquels une conversation peut être rattachée (alias morph => modèle). */
    public const CONTEXTES = [
        'Signalement' => Signalement::class,
        'Atelier' => Atelier::class,
        'Association' => Association::class,
    ];

    protected $connection = 'mongodb';

    protected $collection = 'conversations';

    protected $fillable = [
        'sujet',
        'user_id',
        'admin_id',
        'contexte_type',
        'contexte_id',
        'statut',
        'initiee_par',
        'dernier_message_at',
        'dernier_message_cote',
        'apercu',
        'non_lus_admin',
        'non_lus_utilisateur',
        'lu_admin_at',
        'lu_utilisateur_at',
        'source',
    ];

    protected $casts = [
        'dernier_message_at' => 'datetime',
        'lu_admin_at' => 'datetime',
        'lu_utilisateur_at' => 'datetime',
        'non_lus_admin' => 'integer',
        'non_lus_utilisateur' => 'integer',
    ];

    protected $attributes = [
        'statut' => self::STATUT_OUVERTE,
        'non_lus_admin' => 0,
        'non_lus_utilisateur' => 0,
    ];

    protected static function newFactory()
    {
        return ConversationFactory::new();
    }

    /** Citoyen ou partenaire qui échange avec l'administration (N:1 users). */
    public function interlocuteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    /** Administrateur ayant ouvert ou pris en charge la conversation (N:1 users). */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id', '_id');
    }

    /** Messages du fil (1:N). */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id', '_id');
    }

    /** Élément à l'origine de l'échange : signalement, atelier ou association (polymorphe). */
    public function contexte(): MorphTo
    {
        return $this->morphTo('contexte', 'contexte_type', 'contexte_id', '_id');
    }

    public static function coteDe(User $user): string
    {
        return $user->isAdmin() ? self::COTE_ADMIN : self::COTE_UTILISATEUR;
    }

    public function estOuverte(): bool
    {
        return $this->statut !== self::STATUT_FERMEE;
    }

    public function statutLabel(): string
    {
        return self::STATUTS[$this->statut] ?? self::STATUTS[self::STATUT_OUVERTE];
    }

    public function statutTone(): string
    {
        return $this->estOuverte() ? 'green' : 'purple';
    }

    public function nonLusPour(User $user): int
    {
        return (int) (self::coteDe($user) === self::COTE_ADMIN ? $this->non_lus_admin : $this->non_lus_utilisateur);
    }

    /** Date à laquelle l'AUTRE côté a lu la conversation pour la dernière fois (accusé de lecture). */
    public function luParAutreCote(User $user): ?\Carbon\Carbon
    {
        return self::coteDe($user) === self::COTE_ADMIN ? $this->lu_utilisateur_at : $this->lu_admin_at;
    }

    public function contexteLabel(): ?string
    {
        if (! $this->contexte_type) {
            return null;
        }

        $contexte = $this->contexte;

        return match ($this->contexte_type) {
            'Signalement' => $contexte
                ? 'Signalement : '.$contexte->cibleLabel()
                : 'Signalement supprimé',
            'Atelier', 'Association' => ($this->contexte_type === 'Atelier' ? 'Atelier : ' : 'Association : ')
                .($contexte?->nom ?? 'supprimé(e)'),
            default => null,
        };
    }
}
