<?php

namespace App\Modules\RendezVous\Models;

use App\Modules\Auth\Models\User;
use App\Modules\Vetements\Models\Vetement;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Jenssegers\Mongodb\Eloquent\Model;

/**
 * Modèle RendezVous — représente un rendez-vous de réparation.
 *
 * Champs : vetement_id, atelier_id, service_id, user_id,
 *          date_rdv, duree, statut, commentaire.
 */
class RendezVous extends Model
{
    /* ---------------------------------------------------------------
     |  Constantes de statut
     |--------------------------------------------------------------- */
    public const STATUT_EN_ATTENTE = 'en_attente';

    public const STATUT_CONFIRME = 'confirme';

    public const STATUT_ANNULE = 'annule';

    public const STATUT_TERMINE = 'termine';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_CONFIRME,
        self::STATUT_ANNULE,
        self::STATUT_TERMINE,
    ];

    /** Liste prédéfinie des ateliers disponibles (id => nom) */
    public const ATELIERS = [
        'couture-plus'   => 'Couture Plus',
        'atelier-vert'   => "L'Atelier Vert",
        'fil-et-aiguille' => 'Fil & Aiguille',
    ];

    /** Liste prédéfinie des services disponibles (id => nom) */
    public const SERVICES = [
        'retouche-simple'    => 'Retouche simple',
        'reparation-denim'   => 'Réparation denim',
        'upcycling'          => 'Upcycling créatif',
        'raccommodage'       => 'Raccommodage',
        'ajustement-taille'  => 'Ajustement de taille',
    ];

    /* ---------------------------------------------------------------
     |  Configuration MongoDB
     |--------------------------------------------------------------- */
    protected $connection = 'mongodb';

    protected $collection = 'rendez_vous';

    protected $fillable = [
        'vetement_id',
        'atelier_id',
        'service_id',
        'user_id',
        'date_rdv',
        'duree',
        'statut',
        'commentaire',
    ];

    protected $casts = [
        'date_rdv'   => 'datetime',
        'duree'      => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'statut' => self::STATUT_EN_ATTENTE,
    ];

    /* ---------------------------------------------------------------
     |  Relations
     |--------------------------------------------------------------- */

    /** Le vêtement concerné par le rendez-vous */
    public function vetement(): BelongsTo
    {
        return $this->belongsTo(Vetement::class, 'vetement_id', '_id');
    }

    /** L'utilisateur propriétaire du rendez-vous */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', '_id');
    }

    /* ---------------------------------------------------------------
     |  Accesseurs & helpers
     |--------------------------------------------------------------- */

    /** Libellé lisible du statut */
    public function statutLabel(): string
    {
        return match ($this->statut) {
            self::STATUT_CONFIRME  => 'Confirmé',
            self::STATUT_ANNULE   => 'Annulé',
            self::STATUT_TERMINE  => 'Terminé',
            default               => 'En attente',
        };
    }

    /** Couleur (tone) associée au statut pour le badge */
    public function statutTone(): string
    {
        return match ($this->statut) {
            self::STATUT_CONFIRME => 'green',
            self::STATUT_ANNULE  => 'red',
            self::STATUT_TERMINE => 'gray',
            default              => 'orange',
        };
    }

    /** Nom de l'atelier à partir de l'ID */
    public function atelierNom(): string
    {
        return self::ATELIERS[$this->atelier_id] ?? $this->atelier_id ?? '—';
    }

    /** Nom du service à partir de l'ID */
    public function serviceNom(): string
    {
        if (empty($this->service_id)) {
            return '—';
        }

        return self::SERVICES[$this->service_id] ?? $this->service_id ?? '—';
    }

    /** Durée formatée en heures et minutes */
    public function dureeFormatee(): string
    {
        $heures = intdiv($this->duree, 60);
        $minutes = $this->duree % 60;

        if ($heures > 0 && $minutes > 0) {
            return "{$heures}h{$minutes}min";
        }

        if ($heures > 0) {
            return "{$heures}h";
        }

        return "{$minutes} min";
    }

    /* ---------------------------------------------------------------
     |  Vérifications de permissions par statut
     |--------------------------------------------------------------- */

    /** Vérifie si le RDV peut être modifié */
    public function peutEtreModifie(): bool
    {
        return $this->statut === self::STATUT_EN_ATTENTE;
    }

    /** Vérifie si le RDV peut être annulé */
    public function peutEtreAnnule(): bool
    {
        return in_array($this->statut, [
            self::STATUT_EN_ATTENTE,
            self::STATUT_CONFIRME,
        ], true);
    }

    /** Vérifie si le RDV peut être supprimé */
    public function peutEtreSupprime(): bool
    {
        return in_array($this->statut, [
            self::STATUT_EN_ATTENTE,
            self::STATUT_ANNULE,
        ], true);
    }
}
