<?php

namespace App\Modules\RendezVous\Services;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use App\Modules\Ateliers\Services\AtelierService;
use App\Modules\RendezVous\Models\RendezVous;
use App\Modules\Vetements\Models\Vetement;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RendezVousService
{
    public const OBJECT_ID = '/^[0-9a-fA-F]{24}$/';

    public const PERIODE_A_VENIR = 'a_venir';

    public const PERIODE_PASSES = 'passes';

    public const PERIODES = [self::PERIODE_A_VENIR, self::PERIODE_PASSES];

    public const MOTIF_MAX = 200;

    public function __construct(private AtelierService $ateliers)
    {
    }

    // ---------------------------------------------------------------------
    // Front : prise de rendez-vous par un citoyen
    // ---------------------------------------------------------------------

    /**
     * Seul un atelier actif est réservable ; tout autre identifiant donne null.
     */
    public function atelierReservable(mixed $id): ?Atelier
    {
        if (! is_string($id) || ! preg_match(self::OBJECT_ID, $id)) {
            return null;
        }

        try {
            return $this->ateliers->findActifOrFail($id);
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * Vêtements du citoyen déclarés pour une réparation.
     *
     * @return Collection<int, Vetement>
     */
    public function vetementsReparables(string $userId): Collection
    {
        return Vetement::query()
            ->where('user_id', $userId)
            ->where('intended_action', Vetement::ACTION_REPARATION)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Contrôles métier d'une demande déjà validée sur la forme (identifiants, date, heure).
     * Rien ne vient de l'URL sans être relu en base : atelier actif, service de cet atelier,
     * vêtement du citoyen, créneau futur, dans les horaires et libre.
     *
     * @return array{erreurs: array<string, string>, atelier: ?Atelier, service: ?Service, vetement: ?Vetement}
     */
    public function verifierDemande(string $userId, array $data): array
    {
        $resultat = ['erreurs' => [], 'atelier' => null, 'service' => null, 'vetement' => null];

        $atelier = $this->atelierReservable($data['atelier'] ?? null);

        if (! $atelier) {
            $resultat['erreurs']['atelier'] = "Cet atelier n'est pas disponible à la réservation.";

            return $resultat;
        }

        $resultat['atelier'] = $atelier;
        $service = $this->trouverService((string) $atelier->getKey(), (string) ($data['service'] ?? ''));

        if (! $service) {
            $resultat['erreurs']['service'] = "Ce service n'est pas proposé par cet atelier.";
        }

        $resultat['service'] = $service;

        if (filled($data['vetement'] ?? null)) {
            $resultat['vetement'] = $this->trouverVetementReparable($userId, (string) $data['vetement']);

            if (! $resultat['vetement']) {
                $resultat['erreurs']['vetement'] = 'Choisissez un de vos vêtements déclarés pour une réparation.';
            }
        }

        if ($service) {
            $erreur = $this->verifierCreneau($atelier, (string) $data['date'], (string) $data['heure'], self::dureeService($service));

            if ($erreur) {
                $resultat['erreurs'][$erreur[0]] = $erreur[1];
            }
        }

        return $resultat;
    }

    /**
     * @return array{0: string, 1: string}|null [champ, message] du premier problème, null si le créneau est valable
     */
    public function verifierCreneau(Atelier $atelier, string $date, string $heure, int $duree): ?array
    {
        $debut = RendezVous::moment($date, $heure);

        if (! $debut) {
            return ['date', "La date ou l'heure du rendez-vous est invalide."];
        }

        if ($debut->lessThanOrEqualTo(Atelier::maintenant())) {
            return ['date', 'Le rendez-vous doit être fixé à une date et une heure futures.'];
        }

        if (! $this->dansLesHoraires($atelier, $debut, $duree)) {
            return ['heure', $this->messageHorsHoraires($atelier, $debut, $duree)];
        }

        $conflit = $this->rdvEnConflit($this->rdvsOccupantLaJournee((string) $atelier->getKey(), $date), $debut, $duree);

        if ($conflit) {
            return ['heure', "Ce créneau chevauche un autre rendez-vous de l'atelier ({$conflit->heure} – {$conflit->heureFin()}). Choisissez un autre horaire."];
        }

        return null;
    }

    /**
     * Le rendez-vous entier (début + durée du service) doit tenir dans une même plage d'ouverture.
     */
    public function dansLesHoraires(Atelier $atelier, CarbonImmutable $debut, int $duree): bool
    {
        $debut = $debut->setTimezone(Atelier::FUSEAU_HORAIRE);
        $fin = $debut->addMinutes($duree);

        if ($fin->format('Y-m-d') !== $debut->format('Y-m-d')) {
            return false;
        }

        $heureDebut = $debut->format('H:i');
        $heureFin = $fin->format('H:i');

        foreach ($atelier->horairesSemaine()[Atelier::jourDe($debut)] as [$ouverture, $fermeture]) {
            if ($ouverture <= $heureDebut && $heureFin <= $fermeture) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, RendezVous>  $rdvs
     */
    public function rdvEnConflit(Collection $rdvs, CarbonImmutable $debut, int $duree): ?RendezVous
    {
        $fin = $debut->addMinutes($duree);

        return $rdvs->first(function (RendezVous $rdv) use ($debut, $fin) {
            $autreDebut = $rdv->debut();

            if (! $autreDebut || ! in_array($rdv->statut, RendezVous::STATUTS_OCCUPANT_CRENEAU, true)) {
                return false;
            }

            return $debut->lessThan($autreDebut->addMinutes($rdv->dureeMinutes())) && $autreDebut->lessThan($fin);
        });
    }

    public function creer(string $userId, Atelier $atelier, Service $service, ?Vetement $vetement, array $data): RendezVous
    {
        $rdv = new RendezVous([
            'user_id' => $userId,
            'atelier_id' => (string) $atelier->getKey(),
            'service_id' => (string) $service->getKey(),
            'vetement_id' => $vetement ? (string) $vetement->getKey() : null,
            'date' => (string) $data['date'],
            'heure' => (string) $data['heure'],
            'duree_minutes' => self::dureeService($service),
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
        ]);
        $rdv->statut = RendezVous::STATUT_EN_ATTENTE;

        $this->enregistrer($rdv);

        return $rdv;
    }

    public static function dureeService(Service $service): int
    {
        $duree = (int) $service->duree_estimee;

        return $duree > 0 ? $duree : RendezVous::DUREE_PAR_DEFAUT;
    }

    // ---------------------------------------------------------------------
    // Back office : atelier connecté ou admin
    // ---------------------------------------------------------------------

    /**
     * $atelierId null = tous les ateliers (admin sans filtre).
     */
    public function lister(?string $atelierId, array $filtres, int $perPage = 15): LengthAwarePaginator
    {
        return $this->requeteListe($atelierId, $filtres)
            ->with(['client', 'service', 'vetement', 'atelier'])
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * À venir : tri chronologique ; passés ou sans filtre de période : les plus récents d'abord.
     */
    public function requeteListe(?string $atelierId, array $filtres): Builder
    {
        $query = RendezVous::query();

        if ($atelierId !== null) {
            $query->where('atelier_id', $atelierId);
        }

        if (in_array($filtres['statut'] ?? null, RendezVous::STATUTS, true)) {
            $query->where('statut', $filtres['statut']);
        }

        $maintenant = Atelier::maintenant();
        $jour = $maintenant->format('Y-m-d');
        $heure = $maintenant->format('H:i');
        $periode = $filtres['periode'] ?? null;

        if ($periode === self::PERIODE_A_VENIR) {
            return $query
                ->where(fn (Builder $q) => $q->where('date', '>', $jour)
                    ->orWhere(fn (Builder $q2) => $q2->where('date', $jour)->where('heure', '>=', $heure)))
                ->orderBy('date')
                ->orderBy('heure');
        }

        if ($periode === self::PERIODE_PASSES) {
            $query->where(fn (Builder $q) => $q->where('date', '<', $jour)
                ->orWhere(fn (Builder $q2) => $q2->where('date', $jour)->where('heure', '<', $heure)));
        }

        return $query->orderByDesc('date')->orderByDesc('heure');
    }

    /**
     * @return array{en_attente: int, confirme: int, aujourdhui: int, termine: int}
     */
    public function statistiques(?string $atelierId): array
    {
        $base = fn () => RendezVous::query()->when($atelierId !== null, fn (Builder $q) => $q->where('atelier_id', $atelierId));

        return [
            RendezVous::STATUT_EN_ATTENTE => $base()->where('statut', RendezVous::STATUT_EN_ATTENTE)->count(),
            RendezVous::STATUT_CONFIRME => $base()->where('statut', RendezVous::STATUT_CONFIRME)->count(),
            'aujourdhui' => $base()
                ->where('date', Atelier::maintenant()->format('Y-m-d'))
                ->whereIn('statut', RendezVous::STATUTS_OCCUPANT_CRENEAU)
                ->count(),
            RendezVous::STATUT_TERMINE => $base()->where('statut', RendezVous::STATUT_TERMINE)->count(),
        ];
    }

    /**
     * Résumé de l'espace atelier : nombre de demandes en attente et 3 prochains rendez-vous.
     *
     * @return array{en_attente: int, prochains: Collection<int, RendezVous>}
     */
    public function resumeAtelier(Atelier $atelier): array
    {
        $atelierId = (string) $atelier->getKey();

        return [
            'en_attente' => RendezVous::query()
                ->where('atelier_id', $atelierId)
                ->where('statut', RendezVous::STATUT_EN_ATTENTE)
                ->count(),
            'prochains' => $this->requeteListe($atelierId, ['periode' => self::PERIODE_A_VENIR])
                ->whereIn('statut', [RendezVous::STATUT_EN_ATTENTE, RendezVous::STATUT_CONFIRME])
                ->with(['client', 'service'])
                ->limit(3)
                ->get(),
        ];
    }

    /**
     * Recherche limitée à l'atelier connecté : le RDV d'un autre atelier donne une 404.
     */
    public function findPourAtelier(Atelier $atelier, string $id): RendezVous
    {
        $rdv = RendezVous::query()
            ->where('_id', $id)
            ->where('atelier_id', (string) $atelier->getKey())
            ->firstOrFail();

        return $rdv->setRelation('atelier', $atelier);
    }

    public function findOrFail(string $id): RendezVous
    {
        return RendezVous::query()->with('atelier')->findOrFail($id);
    }

    /**
     * $atelier : atelier du compte connecté (null pour l'admin). Un RDV qui ne lui appartient pas donne une 404.
     */
    public function changerStatut(RendezVous $rdv, string $statut, ?string $motif = null, ?Atelier $atelier = null): RendezVous
    {
        if ($atelier !== null && (string) $rdv->atelier_id !== (string) $atelier->getKey()) {
            throw (new ModelNotFoundException())->setModel(RendezVous::class, [(string) $rdv->getKey()]);
        }

        if (! $rdv->peutPasserA($statut)) {
            throw ValidationException::withMessages([
                'statut' => sprintf(
                    'Un rendez-vous « %s » ne peut pas passer au statut « %s ».',
                    $rdv->statutLabel(),
                    RendezVous::libelleStatut($statut)
                ),
            ]);
        }

        $motif = is_string($motif) ? trim($motif) : '';

        if ($statut === RendezVous::STATUT_REFUSE && $motif === '') {
            throw ValidationException::withMessages(['motif' => 'Indiquez le motif du refus.']);
        }

        $rdv->statut = $statut;

        if (in_array($statut, [RendezVous::STATUT_REFUSE, RendezVous::STATUT_ANNULE], true) && $motif !== '') {
            $rdv->motif = mb_substr($motif, 0, self::MOTIF_MAX);
        }

        $this->enregistrer($rdv);

        return $rdv;
    }

    /**
     * @return Collection<int, Atelier>
     */
    public function ateliersPourFiltre(): Collection
    {
        return Atelier::query()->orderBy('nom')->get(['nom', 'ville']);
    }

    // ---------------------------------------------------------------------
    // Accès base (surchargés dans les tests)
    // ---------------------------------------------------------------------

    protected function trouverService(string $atelierId, string $serviceId): ?Service
    {
        if (! preg_match(self::OBJECT_ID, $serviceId)) {
            return null;
        }

        return Service::query()->where('_id', $serviceId)->where('atelier_id', $atelierId)->first();
    }

    protected function trouverVetementReparable(string $userId, string $vetementId): ?Vetement
    {
        if (! preg_match(self::OBJECT_ID, $vetementId)) {
            return null;
        }

        return Vetement::query()
            ->where('_id', $vetementId)
            ->where('user_id', $userId)
            ->where('intended_action', Vetement::ACTION_REPARATION)
            ->first();
    }

    /**
     * @return Collection<int, RendezVous>
     */
    protected function rdvsOccupantLaJournee(string $atelierId, string $date): Collection
    {
        return RendezVous::query()
            ->where('atelier_id', $atelierId)
            ->where('date', $date)
            ->whereIn('statut', RendezVous::STATUTS_OCCUPANT_CRENEAU)
            ->get();
    }

    protected function enregistrer(RendezVous $rdv): void
    {
        $rdv->save();
    }

    private function messageHorsHoraires(Atelier $atelier, CarbonImmutable $debut, int $duree): string
    {
        $jour = Atelier::jourDe($debut);
        $plages = $atelier->horairesSemaine()[$jour];

        if ($plages === []) {
            return "L'atelier est fermé le {$jour}. Choisissez un autre jour.";
        }

        $horaires = implode(', ', array_map(fn (array $p) => "{$p[0]} – {$p[1]}", $plages));

        return sprintf(
            "Le rendez-vous (%s) doit se dérouler pendant les horaires d'ouverture du %s : %s.",
            Service::formatDuree($duree),
            $jour,
            $horaires
        );
    }
}
