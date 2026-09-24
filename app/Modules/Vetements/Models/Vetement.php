<?php

namespace App\Modules\Vetements\Models;

use App\Modules\Auth\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Jenssegers\Mongodb\Eloquent\Model;

class Vetement extends Model
{
    public const STATUS_EN_ATTENTE = 'en_attente';

    public const STATUS_EN_REPARATION = 'en_reparation';

    public const STATUS_REPARE = 'repare';

    public const STATUS_DONNE = 'donne';

    public const STATUS_RECYCLE = 'recycle';

    public const ACTION_REPARATION = 'reparation';

    public const ACTION_DON = 'don';

    public const INTENDED_ACTIONS = [
        self::ACTION_REPARATION,
        self::ACTION_DON,
    ];

    public const STATUSES = [
        self::STATUS_EN_ATTENTE,
        self::STATUS_EN_REPARATION,
        self::STATUS_REPARE,
        self::STATUS_DONNE,
        self::STATUS_RECYCLE,
    ];

    /** @var list<string> */
    public const MATERIALS = [
        'Coton',
        'Denim',
        'Laine',
        'Polyester',
        'Lin',
        'Soie',
        'Cuir',
        'Synthétique',
        'Mélange coton',
        'Cachemire',
    ];

    protected $connection = 'mongodb';

    protected $collection = 'vetements';

    protected $fillable = [
        'user_id',
        'type',
        'size',
        'condition_label',
        'material',
        'description',
        'status',
        'image_url',
        'image_path',
        'intended_action',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_EN_ATTENTE,
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    public function cycleEvents(): HasMany
    {
        return $this->hasMany(CycleVieEvent::class, 'vetement_id', '_id')
            ->orderBy('occurred_at')
            ->orderBy('step_order');
    }

    public function displayName(): string
    {
        $label = trim($this->type.($this->size ? ' · Taille '.$this->size : ''));

        return $label !== '' ? $label : 'Vêtement';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_EN_REPARATION => 'En réparation',
            self::STATUS_REPARE => 'Réparé',
            self::STATUS_DONNE => 'Donné',
            self::STATUS_RECYCLE => 'Recyclé',
            default => 'En attente',
        };
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            self::STATUS_EN_REPARATION => 'blue',
            self::STATUS_REPARE => 'green',
            self::STATUS_DONNE => 'purple',
            self::STATUS_RECYCLE => 'gray',
            default => 'orange',
        };
    }

    public function timelineProgress(): int
    {
        $count = $this->relationLoaded('cycleEvents')
            ? $this->cycleEvents->count()
            : $this->cycleEvents()->count();

        return min(max($count, 1), 4);
    }

    public function ownerShortName(): string
    {
        if (! $this->relationLoaded('owner') || ! $this->owner) {
            return '—';
        }

        $parts = preg_split('/\s+/', trim($this->owner->name)) ?: [];
        if (count($parts) === 0) {
            return '—';
        }

        $first = $parts[0];
        $lastInitial = isset($parts[1]) ? mb_substr($parts[1], 0, 1).'.' : '';

        return trim($first.' '.$lastInitial);
    }

    public function photoUrl(): string
    {
        if (! empty($this->image_path)) {
            return asset('storage/'.$this->image_path);
        }

        if (! empty($this->image_url)) {
            return $this->image_url;
        }

        return asset('icon.svg');
    }

    public function intendedActionLabel(): string
    {
        return match ($this->intended_action) {
            self::ACTION_DON => 'Don',
            self::ACTION_REPARATION => 'Réparation',
            default => 'À définir',
        };
    }

    public function intendedActionTone(): string
    {
        return match ($this->intended_action) {
            self::ACTION_DON => 'purple',
            self::ACTION_REPARATION => 'blue',
            default => 'orange',
        };
    }

    public function nextStepUrl(): ?string
    {
        return match ($this->intended_action) {
            self::ACTION_DON => route('front.dons'),
            self::ACTION_REPARATION => route('front.ateliers'),
            default => null,
        };
    }

    public function nextStepLabel(): ?string
    {
        return match ($this->intended_action) {
            self::ACTION_DON => 'Proposer un don',
            self::ACTION_REPARATION => 'Trouver un atelier',
            default => null,
        };
    }
}
