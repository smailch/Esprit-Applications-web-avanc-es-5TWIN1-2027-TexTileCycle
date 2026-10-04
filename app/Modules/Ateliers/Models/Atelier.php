<?php

namespace App\Modules\Ateliers\Models;

use App\Modules\Auth\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\AtelierFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Jenssegers\Mongodb\Eloquent\Model;

class Atelier extends Model
{
    use HasFactory;

    public const STATUT_EN_ATTENTE = 'en_attente';

    public const STATUT_ACTIF = 'actif';

    public const STATUT_SUSPENDU = 'suspendu';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_ACTIF,
        self::STATUT_SUSPENDU,
    ];

    public const JOURS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

    /**
     * Les horaires sont saisis en heure locale tunisienne (l'application tourne en UTC).
     */
    public const FUSEAU_HORAIRE = 'Africa/Tunis';

    private const AVATAR_TONES = ['green', 'blue', 'purple', 'orange'];

    /**
     * Attributs calculés à la volée (ex. par AtelierService::search), jamais persistés.
     */
    public const TRANSIENT_ATTRIBUTES = ['distance_km'];

    protected $connection = 'mongodb';

    protected $collection = 'ateliers';

    protected $fillable = [
        'user_id',
        'nom',
        'specialite',
        'description',
        'adresse',
        'ville',
        'latitude',
        'longitude',
        'location',
        'telephone',
        'horaires',
        'note_moyenne',
        'nb_avis',
        'statut',
    ];

    /**
     * Pas de cast 'array' sur horaires : avec laravel-mongodb 3.9 il serait stocké
     * en chaîne JSON. MongoDB stocke le tableau nativement et le relit en array PHP.
     */
    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'note_moyenne' => 'float',
        'nb_avis' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'statut' => self::STATUT_EN_ATTENTE,
        'note_moyenne' => 0,
        'nb_avis' => 0,
    ];

    protected static function newFactory()
    {
        return AtelierFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (Atelier $atelier) {
            $atelier->syncLocation();

            foreach (self::TRANSIENT_ATTRIBUTES as $attribute) {
                unset($atelier->attributes[$attribute]);
            }
        });
    }

    /**
     * location est toujours dérivé de latitude/longitude (GeoJSON : [lng, lat]).
     */
    public function syncLocation(): void
    {
        $lat = $this->attributes['latitude'] ?? null;
        $lng = $this->attributes['longitude'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            $this->attributes['location'] = null;

            return;
        }

        $this->attributes['latitude'] = (float) $lat;
        $this->attributes['longitude'] = (float) $lng;
        $this->attributes['location'] = [
            'type' => 'Point',
            'coordinates' => [(float) $lng, (float) $lat],
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'atelier_id', '_id');
    }

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('statut', self::STATUT_ACTIF);
    }

    public function scopeNoteMin(Builder $query, float|int|string|null $min): Builder
    {
        if ($min === null || $min === '') {
            return $query;
        }

        return $query->where('note_moyenne', '>=', (float) $min);
    }

    public function statutLabel(): string
    {
        return self::libelleStatut((string) $this->statut);
    }

    public static function libelleStatut(string $statut): string
    {
        return match ($statut) {
            self::STATUT_ACTIF => 'Actif',
            self::STATUT_SUSPENDU => 'Suspendu',
            default => 'En attente',
        };
    }

    public function statutTone(): string
    {
        return match ($this->statut) {
            self::STATUT_ACTIF => 'green',
            self::STATUT_SUSPENDU => 'purple',
            default => 'orange',
        };
    }

    /**
     * Le champ specialite s'il est renseigné, sinon les noms des 2 premiers services.
     */
    public function specialiteAffichee(): string
    {
        $specialite = trim((string) ($this->attributes['specialite'] ?? ''));

        if ($specialite !== '') {
            return $specialite;
        }

        $services = $this->relationLoaded('services')
            ? $this->services->take(2)
            : $this->services()->orderBy('created_at')->limit(2)->get();

        return $services->pluck('nom')->filter()->implode(' & ');
    }

    public function noteFormatee(): string
    {
        return number_format((float) $this->note_moyenne, 1, ',', '');
    }

    /**
     * Les 7 jours dans l'ordre ; les plages mal formées sont ignorées.
     *
     * @return array<string, list<array{0:string,1:string}>>
     */
    public function horairesSemaine(): array
    {
        $horaires = is_array($this->horaires) ? $this->horaires : [];
        $semaine = [];

        foreach (self::JOURS as $jour) {
            $plages = is_array($horaires[$jour] ?? null) ? $horaires[$jour] : [];

            $semaine[$jour] = array_values(array_filter(
                $plages,
                fn ($plage) => is_array($plage) && count($plage) === 2 && is_string($plage[0] ?? null) && is_string($plage[1] ?? null)
            ));
        }

        return $semaine;
    }

    public static function maintenant(): CarbonImmutable
    {
        return CarbonImmutable::now(self::FUSEAU_HORAIRE);
    }

    public static function jourDe(CarbonInterface $moment): string
    {
        return self::JOURS[CarbonImmutable::instance($moment)->setTimezone(self::FUSEAU_HORAIRE)->dayOfWeekIso - 1];
    }

    public function estOuvert(?CarbonInterface $moment = null): bool
    {
        $moment = $moment ? CarbonImmutable::instance($moment)->setTimezone(self::FUSEAU_HORAIRE) : self::maintenant();
        $heure = $moment->format('H:i');

        foreach ($this->horairesSemaine()[self::jourDe($moment)] as [$debut, $fin]) {
            if ($debut <= $heure && $heure < $fin) {
                return true;
            }
        }

        return false;
    }

    public function prixMinimal(): ?float
    {
        $prix = $this->services->pluck('prix_estime')->filter(fn ($p) => is_numeric($p));

        return $prix->isEmpty() ? null : (float) $prix->min();
    }

    public function initiales(): string
    {
        $mots = preg_split('/[\s\'’-]+/u', trim((string) $this->nom), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $significatifs = array_values(array_filter($mots, fn ($m) => mb_strlen($m) > 2));
        $mots = count($significatifs) >= 2 ? $significatifs : $mots;
        $initiales = '';

        foreach (array_slice($mots, 0, 2) as $mot) {
            $initiales .= mb_strtoupper(mb_substr($mot, 0, 1));
        }

        return $initiales !== '' ? $initiales : 'AT';
    }

    public function avatarTone(): string
    {
        return self::AVATAR_TONES[crc32((string) $this->getKey()) % count(self::AVATAR_TONES)];
    }

    public function telephoneLien(): ?string
    {
        $numero = preg_replace('/[^0-9+]/', '', (string) $this->telephone);

        return $numero !== '' ? 'tel:'.$numero : null;
    }

    public static function formatPrix(float $prix): string
    {
        return self::formatMontant($prix).' TND';
    }

    public static function formatMontant(float $prix): string
    {
        $decimales = floor($prix) == $prix ? 0 : 2;

        return number_format($prix, $decimales, ',', ' ');
    }
}
