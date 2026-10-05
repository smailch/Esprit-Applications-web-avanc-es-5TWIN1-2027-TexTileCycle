<?php

namespace App\Modules\Dons\Models;

use App\Modules\Associations\Models\Association;
use App\Modules\Auth\Models\User;
use App\Modules\Vetements\Models\Vetement;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jenssegers\Mongodb\Eloquent\Model;

class Don extends Model
{
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_ACCEPTE   = 'accepte';
    public const STATUT_REFUSE    = 'refuse';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_ACCEPTE,
        self::STATUT_REFUSE,
    ];

    protected $connection = 'mongodb';
    protected $collection = 'dons';

    protected $fillable = [
        'vetement_id',
        'association_id',
        'user_id',
        'statut',
        'message',
        'date_reponse',
    ];

    protected $casts = [
        'date_reponse' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    protected $attributes = [
        'statut' => self::STATUT_EN_ATTENTE,
    ];

    /* ── Relations ─────────────────────────────────────────────────── */

    public function vetement(): BelongsTo
    {
        return $this->belongsTo(Vetement::class, 'vetement_id', '_id');
    }

    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class, 'association_id', '_id');
    }

    public function donateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    /* ── Helpers ────────────────────────────────────────────────────── */

    public function statutLabel(): string
    {
        return match ($this->statut) {
            self::STATUT_ACCEPTE => 'Accepté',
            self::STATUT_REFUSE  => 'Refusé',
            default              => 'En attente',
        };
    }

    public function statutTone(): string
    {
        return match ($this->statut) {
            self::STATUT_ACCEPTE => 'green',
            self::STATUT_REFUSE  => 'red',
            default              => 'orange',
        };
    }

    public function isPending(): bool
    {
        return $this->statut === self::STATUT_EN_ATTENTE;
    }
}
