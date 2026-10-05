<?php

namespace Tests\Concerns;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Auth\Models\User;
use App\Modules\RendezVous\Models\RendezVous;
use App\Modules\Vetements\Models\Vetement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Modèles en mémoire (jamais sauvegardés) pour les tests du module RendezVous.
 * Horloge figée au lundi 5 octobre 2026, 10:00 heure de Tunis.
 *
 * La classe utilisatrice doit implémenter IdentifiantsRendezVous
 * (constantes ID_* et NOM_PIEGE référencées via self::).
 */
trait FabriqueRendezVous
{
    protected function figerHorloge(string $moment = '2026-10-05 10:00'): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($moment, Atelier::FUSEAU_HORAIRE));
    }

    protected function utilisateur(string $role = User::ROLE_CITOYEN, string $id = self::ID_CITOYEN, string $nom = 'Amira Trabelsi'): User
    {
        $user = new User(['name' => $nom, 'email' => 'amira@example.test', 'role' => $role, 'phone' => '+216 20 111 222']);
        $user->setAttribute('_id', $id);

        return $user;
    }

    protected function prestation(string $id = self::ID_SERVICE, string $nom = 'Ourlet <b>express</b>', int $duree = 45, float $prix = 15, string $atelierId = self::ID_ATELIER): Service
    {
        $service = new Service(['nom' => $nom, 'prix_estime' => $prix, 'duree_estimee' => $duree]);
        $service->setAttribute('_id', $id);
        $service->atelier_id = $atelierId;
        $service->exists = true;

        return $service;
    }

    protected function atelier(string $id = self::ID_ATELIER, string $statut = Atelier::STATUT_ACTIF, ?array $services = null): Atelier
    {
        $journee = [['09:00', '12:00'], ['14:00', '18:00']];
        $atelier = new Atelier([
            'nom' => self::NOM_PIEGE,
            'ville' => 'La Marsa',
            'adresse' => '45 Avenue Habib Bourguiba',
            'telephone' => '+216 71 774 210',
            'horaires' => array_fill_keys(['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'], $journee)
                + ['samedi' => [['09:00', '13:00']], 'dimanche' => []],
        ]);
        $atelier->setAttribute('_id', $id);
        $atelier->user_id = self::ID_COMPTE_ATELIER;
        $atelier->statut = $statut;
        $atelier->exists = true;

        return $atelier->setRelation('services', new Collection($services ?? [
            $this->prestation(),
            $this->prestation(self::ID_SERVICE_2, 'Changer une fermeture', 30, 18),
        ]));
    }

    protected function vetement(): Vetement
    {
        $vetement = new Vetement(['type' => 'Veste en jean', 'size' => 'M', 'intended_action' => Vetement::ACTION_REPARATION]);
        $vetement->setAttribute('_id', self::ID_VETEMENT);
        $vetement->user_id = self::ID_CITOYEN;
        $vetement->exists = true;

        return $vetement;
    }

    protected function rdv(
        string $statut = RendezVous::STATUT_EN_ATTENTE,
        string $date = '2026-10-06',
        string $heure = '10:00',
        int $duree = 45,
        string $atelierId = self::ID_ATELIER,
        string $id = self::ID_RDV,
    ): RendezVous {
        $rdv = new RendezVous(['date' => $date, 'heure' => $heure, 'duree_minutes' => $duree, 'notes' => 'Ourlet <i>abîmé</i>']);
        $rdv->setAttribute('_id', $id);
        $rdv->user_id = self::ID_CITOYEN;
        $rdv->atelier_id = $atelierId;
        $rdv->service_id = self::ID_SERVICE;
        $rdv->statut = $statut;
        $rdv->exists = true;

        return $rdv;
    }
}
