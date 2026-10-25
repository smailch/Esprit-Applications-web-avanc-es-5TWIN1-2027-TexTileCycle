<?php

namespace App\Modules\Ateliers\Models;

use Illuminate\Support\Carbon;
use Jenssegers\Mongodb\Eloquent\Model;

/**
 * Passeport de réparation (collection "passeports") : certificat d'une réparation terminée, consultable
 * par QR code sur /passeport/{code}. Un passeport par rendez-vous.
 *
 * - code : jeton aléatoire (CSPRNG, ~131 bits), seule clé publique ; jamais l'identifiant Mongo.
 * - instantane : informations publiques figées à la création (vêtement, réparation, atelier, date) ;
 *   elles ne changent plus, même si le rendez-vous ou le vêtement est modifié ensuite.
 * - user_id et vetement_id restent internes (page « Mes passeports », historique de la pièce) : jamais affichés.
 */
class Passeport extends Model
{
    public const LONGUEUR_CODE = 22;

    /** Format du code dans les routes (Str::random : lettres et chiffres). */
    public const FORMAT_CODE = '[A-Za-z0-9]{22}';

    /** Durées de garantie proposées à l'atelier (en jours) ; 0 = sans garantie. */
    public const GARANTIES = [15, 30, 60, 90];

    public const MOT_MAX = 300;

    protected $connection = 'mongodb';

    protected $collection = 'passeports';

    protected $fillable = [
        'code',
        'atelier_id',
        'rendez_vous_id',
        'vetement_id',
        'user_id',
        'instantane',
        'revoque_le',
    ];

    protected $casts = [
        'revoque_le' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function estRevoque(): bool
    {
        return $this->revoque_le !== null;
    }

    /** Une valeur de l'instantané, en texte (chaîne vide si absente). */
    public function info(string $cle): string
    {
        $instantane = is_array($this->instantane) ? $this->instantane : [];
        $valeur = $instantane[$cle] ?? '';

        return is_scalar($valeur) ? trim((string) $valeur) : '';
    }

    public function vetementAffiche(): string
    {
        $type = $this->info('vetement');
        $taille = $this->info('taille');

        $libelle = trim($type.($taille !== '' ? ' · Taille '.$taille : ''));

        return $libelle !== '' ? $libelle : 'Vêtement';
    }

    public function reparationAffichee(): string
    {
        return $this->info('reparation') !== '' ? $this->info('reparation') : 'Réparation';
    }

    public function dateReparation(): ?Carbon
    {
        $date = $this->info('repare_le');

        try {
            return $date !== '' ? Carbon::parse($date) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** « 16 octobre 2026 », en français quelle que soit la langue de l'application. */
    public function dateReparationAffichee(): string
    {
        return $this->dateReparation()?->locale('fr')->isoFormat('D MMMM YYYY') ?? '—';
    }

    public function garantieJours(): int
    {
        $jours = (int) $this->info('garantie_jours');

        return in_array($jours, self::GARANTIES, true) ? $jours : 0;
    }

    /** Dernier jour couvert par la garantie de l'atelier (null sans garantie ou sans date). */
    public function garantieFin(): ?Carbon
    {
        $date = $this->dateReparation();

        return $date && $this->garantieJours() > 0 ? $date->copy()->addDays($this->garantieJours()) : null;
    }

    public function garantieActive(): bool
    {
        return $this->garantieFin()?->endOfDay()->isFuture() ?? false;
    }

    /** « 1 h 30 » ou « 45 min » : durée estimée du service au catalogue de l'atelier. */
    public function dureeServiceAffichee(): string
    {
        $minutes = (int) $this->info('service_duree');

        if ($minutes <= 0) {
            return '';
        }

        $heures = intdiv($minutes, 60);
        $reste = $minutes % 60;

        return match (true) {
            $heures === 0 => $reste.' min',
            $reste === 0 => $heures.' h',
            default => $heures.' h '.str_pad((string) $reste, 2, '0', STR_PAD_LEFT),
        };
    }

    /** Repère court imprimé sous le QR (8 premiers caractères du code) : aide à associer une étiquette à sa pièce. */
    public function numeroCourt(): string
    {
        return strtoupper(substr((string) $this->code, 0, 4).'-'.substr((string) $this->code, 4, 4));
    }
}
