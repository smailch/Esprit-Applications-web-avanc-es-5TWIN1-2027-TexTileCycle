<?php

namespace App\Modules\Vetements\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jenssegers\Mongodb\Eloquent\Model;

class CycleVieEvent extends Model
{
    public const STEP_DECLARE = 'declare';

    public const STEP_ANALYSE = 'analyse';

    public const STEP_ACTION = 'action';

    public const STEP_TERMINE = 'termine';

    protected $connection = 'mongodb';

    protected $collection = 'cycle_vie_events';

    protected $fillable = [
        'vetement_id',
        'step_key',
        'step_order',
        'title',
        'description',
        'status_snapshot',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'step_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function vetement(): BelongsTo
    {
        return $this->belongsTo(Vetement::class, 'vetement_id', '_id');
    }

    public function icon(): string
    {
        if ($this->step_key === self::STEP_DECLARE) {
            return 'plus-circle';
        }

        if ($this->step_key === self::STEP_ANALYSE) {
            return 'route';
        }

        if ($this->step_key === self::STEP_TERMINE) {
            return match ($this->status_snapshot) {
                Vetement::STATUS_DONNE => 'gift',
                Vetement::STATUS_RECYCLE => 'recycle',
                default => 'check-circle-2',
            };
        }

        return match ($this->status_snapshot) {
            Vetement::STATUS_EN_REPARATION => 'wrench',
            Vetement::STATUS_DONNE => 'heart-handshake',
            default => 'circle-dot',
        };
    }

    public function occurredLabel(): string
    {
        return $this->occurred_at?->format('d/m/Y · H:i') ?? '';
    }
}
