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
}
