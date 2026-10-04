<?php

namespace App\Modules\RendezVous\Models;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Auth\Models\User;
use App\Modules\Vetements\Models\Vetement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jenssegers\Mongodb\Eloquent\Model;

/**
 * Demande de rendez-vous d'un citoyen auprès d'un atelier (collection "rendez_vous").
 * date (Y-m-d) et heure (H:i) sont en heure locale de l'atelier (Atelier::FUSEAU_HORAIRE),
 * stockées en chaînes : le tri lexicographique correspond au tri chronologique.
 */
class RendezVous extends Model
{
    public const STATUT_EN_ATTENTE = 'en_attente';

    public const STATUT_CONFIRME = 'confirme';

    public const STATUT_REFUSE = 'refuse';

    public const STATUT_TERMINE = 'termine';

    public const STATUT_ANNULE = 'annule';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_CONFIRME,
        self::STATUT_REFUSE,
        self::STATUT_TERMINE,
        self::STATUT_ANNULE,
    ];

    /**
     * Statuts qui occupent un créneau de l'atelier (un RDV refusé ou annulé le libère).
     */
    public const STATUTS_OCCUPANT_CRENEAU = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_CONFIRME,
        self::STATUT_TERMINE,
    ];

    /**
     * Seules transitions autorisées : statut actuel => statuts suivants possibles.
     */
    public const TRANSITIONS = [
        self::STATUT_EN_ATTENTE => [self::STATUT_CONFIRME, self::STATUT_REFUSE],
        self::STATUT_CONFIRME => [self::STATUT_TERMINE, self::STATUT_ANNULE],
    ];

    /**
     * Durée retenue quand le service n'a pas de duree_estimee exploitable.
     */
    public const DUREE_PAR_DEFAUT = 30;

    private const ID_FIELDS = ['user_id', 'atelier_id', 'service_id', 'vetement_id'];

    private const MOIS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

    protected $connection = 'mongodb';

    protected $collection = 'rendez_vous';

    protected $fillable = [
        'user_id',
        'atelier_id',
        'service_id',
        'vetement_id',
        'date',
        'heure',
        'duree_minutes',
        'notes',
        'statut',
        'motif',
    ];

    protected $casts = [
        'duree_minutes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'statut' => self::STATUT_EN_ATTENTE,
    ];

    protected static function booted(): void
    {
        static::saving(function (RendezVous $rdv) {
            foreach (self::ID_FIELDS as $champ) {
                $valeur = $rdv->attributes[$champ] ?? null;
                $rdv->attributes[$champ] = $valeur === null || $valeur === '' ? null : (string) $valeur;
            }
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    public function atelier(): BelongsTo
    {
        return $this->belongsTo(Atelier::class, 'atelier_id', '_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id', '_id');
    }

    public function vetement(): BelongsTo
    {
        return $this->belongsTo(Vetement::class, 'vetement_id', '_id');
    }

    public function statutLabel(): string
    {
        return self::libelleStatut((string) $this->statut);
    }

    public static function libelleStatut(string $statut): string
    {
        return match ($statut) {
            self::STATUT_CONFIRME => 'Confirmé',
            self::STATUT_REFUSE => 'Refusé',
            self::STATUT_TERMINE => 'Terminé',
            self::STATUT_ANNULE => 'Annulé',
            default => 'En attente',
        };
    }

    public function statutTone(): string
    {
        return match ($this->statut) {
            self::STATUT_CONFIRME => 'blue',
            self::STATUT_TERMINE => 'green',
            self::STATUT_REFUSE, self::STATUT_ANNULE => 'purple',
            default => 'orange',
        };
    }

    public static function transitionAutorisee(string $de, string $vers): bool
    {
        return in_array($vers, self::TRANSITIONS[$de] ?? [], true);
    }

    public function peutPasserA(string $statut): bool
    {
        return self::transitionAutorisee((string) $this->statut, $statut);
    }

    public function dureeMinutes(): int
    {
        $duree = (int) $this->duree_minutes;

        return $duree > 0 ? $duree : self::DUREE_PAR_DEFAUT;
    }

    public function debut(): ?CarbonImmutable
    {
        return self::moment((string) $this->date, (string) $this->heure);
    }

    public function heureFin(): ?string
    {
        return $this->debut()?->addMinutes($this->dureeMinutes())->format('H:i');
    }

    public function dateFormatee(): string
    {
        $debut = $this->debut();

        if (! $debut) {
            return (string) $this->date;
        }

        return ucfirst(Atelier::jourDe($debut)).' '.$debut->day.' '.self::MOIS[$debut->month - 1].' '.$debut->year;
    }

    /**
     * Combine une date Y-m-d et une heure H:i en heure locale de l'atelier ; null si mal formées.
     */
    public static function moment(string $date, string $heure): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $heure)) {
            return null;
        }

        $moment = CarbonImmutable::createFromFormat('!Y-m-d H:i', $date.' '.$heure, Atelier::FUSEAU_HORAIRE);

        return $moment && $moment->format('Y-m-d H:i') === $date.' '.$heure ? $moment : null;
    }
}
