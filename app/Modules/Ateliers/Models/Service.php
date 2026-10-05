<?php

namespace App\Modules\Ateliers\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jenssegers\Mongodb\Eloquent\Model;

/**
 * Modèle Eloquent : prestation proposée par un atelier (collection "services").
 * À ne pas confondre avec le dossier Services/ du module, qui contient la logique métier.
 */
class Service extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'services';

    protected $fillable = [
        'atelier_id',
        'nom',
        'description',
        'prix_estime',
        'duree_estimee',
    ];

    /**
     * prix_estime en TND, duree_estimee en minutes.
     */
    protected $casts = [
        'prix_estime' => 'float',
        'duree_estimee' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function newFactory()
    {
        return ServiceFactory::new();
    }

    public function atelier(): BelongsTo
    {
        return $this->belongsTo(Atelier::class, 'atelier_id', '_id');
    }

    public function prixFormate(): ?string
    {
        return is_numeric($this->prix_estime) ? Atelier::formatPrix((float) $this->prix_estime) : null;
    }

    public function dureeFormatee(): ?string
    {
        return self::formatDuree((int) $this->duree_estimee);
    }

    public static function formatDuree(int $minutes): ?string
    {
        if ($minutes <= 0) {
            return null;
        }

        $heures = intdiv($minutes, 60);
        $reste = $minutes % 60;

        return match (true) {
            $heures === 0 => "{$reste} min",
            $reste === 0 => "{$heures} h",
            default => sprintf('%d h %02d', $heures, $reste),
        };
    }
}
