<?php

namespace App\Modules\RendezVous\Services;

use App\Modules\Ateliers\Models\Service;
use App\Modules\Auth\Models\User;
use App\Modules\RendezVous\Models\RendezVous;
use App\Modules\Vetements\Models\Vetement;
use App\Modules\Vetements\Services\VetementService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class RendezVousService
{
    public function __construct(
        private VetementService $vetements
    ) {
    }

    public function creerPourCitoyen(User $user, array $data): RendezVous
    {
        $vetement = Vetement::query()
            ->where('_id', $data['vetement_id'])
            ->where('user_id', (string) $user->getKey())
            ->first();

        if (! $vetement) {
            throw ValidationException::withMessages([
                'vetement_id' => 'Ce vêtement ne vous appartient pas.',
            ]);
        }

        $this->assertServiceDeLatelier($data['service_id'] ?? null, (string) $data['atelier_id']);

        $rdv = RendezVous::create([
            'vetement_id' => (string) $vetement->getKey(),
            'atelier_id' => (string) $data['atelier_id'],
            'service_id' => $data['service_id'] ?? null,
            'user_id' => (string) $user->getKey(),
            'date_rdv' => $data['date_rdv'],
            'duree' => $data['duree'],
            'statut' => RendezVous::STATUT_EN_ATTENTE,
            'commentaire' => $data['commentaire'] ?? null,
        ]);

        $this->vetements->assignerPourReparation($vetement, (string) $data['atelier_id']);

        return $rdv;
    }

    public function listerPourAtelier(string $atelierId, ?string $statut = null): Collection
    {
        $query = RendezVous::query()
            ->where('atelier_id', $atelierId)
            ->with(['vetement', 'user', 'service'])
            ->orderBy('date_rdv');

        if ($statut && in_array($statut, RendezVous::STATUTS, true)) {
            $query->where('statut', $statut);
        }

        return $query->get();
    }

    public function listerPourAdmin(?string $statut = null): Collection
    {
        $query = RendezVous::query()
            ->with(['vetement', 'user', 'service', 'atelier'])
            ->orderByDesc('date_rdv');

        if ($statut && in_array($statut, RendezVous::STATUTS, true)) {
            $query->where('statut', $statut);
        }

        return $query->limit(80)->get();
    }

    public function countsPourAtelier(string $atelierId): array
    {
        $parStatut = RendezVous::query()
            ->where('atelier_id', $atelierId)
            ->get(['statut'])
            ->countBy('statut');

        $counts = [];
        foreach (RendezVous::STATUTS as $statut) {
            $counts[$statut] = (int) ($parStatut[$statut] ?? 0);
        }
        $counts['total'] = array_sum($counts);

        return $counts;
    }

    public function changerStatutPourAtelier(string $rdvId, string $atelierId, string $statut): RendezVous
    {
        $rdv = RendezVous::query()->with('vetement')->findOrFail($rdvId);

        if (! $rdv->appartientALatelier($atelierId)) {
            abort(403, 'Ce rendez-vous n\'appartient pas à votre atelier.');
        }

        if (! in_array($statut, $rdv->transitionsAtelier(), true)) {
            abort(422, 'Cette action n\'est pas possible pour le statut actuel du rendez-vous.');
        }

        $rdv->statut = $statut;
        $rdv->save();

        if ($rdv->vetement) {
            $this->vetements->synchroniserDepuisRendezVous($rdv->vetement, $statut);
        }

        return $rdv->fresh(['vetement', 'user', 'service']) ?? $rdv;
    }

    public function changerStatutPourAdmin(string $rdvId, string $statut): RendezVous
    {
        if (! in_array($statut, RendezVous::STATUTS, true)) {
            abort(422, 'Statut de rendez-vous invalide.');
        }

        $rdv = RendezVous::query()->with('vetement')->findOrFail($rdvId);
        $rdv->statut = $statut;
        $rdv->save();

        if ($rdv->vetement) {
            $this->vetements->synchroniserDepuisRendezVous($rdv->vetement, $statut);
        }

        return $rdv->fresh(['vetement', 'user', 'service', 'atelier']) ?? $rdv;
    }

    private function assertServiceDeLatelier(?string $serviceId, string $atelierId): void
    {
        if ($serviceId === null || $serviceId === '') {
            return;
        }

        $service = Service::query()->find($serviceId);

        if ($service && (string) $service->atelier_id !== (string) $atelierId) {
            throw ValidationException::withMessages([
                'service_id' => 'Ce service n\'est pas proposé par l\'atelier choisi.',
            ]);
        }
    }
}
