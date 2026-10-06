<?php

namespace App\Modules\Vetements\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Vetements\Models\CycleVieEvent;
use App\Modules\Vetements\Models\Vetement;
use Illuminate\Support\Collection;

class VetementService
{
    public function listForOwner(User $user): Collection
    {
        return Vetement::query()
            ->where('user_id', (string) $user->getKey())
            ->with('cycleEvents')
            ->orderByDesc('created_at')
            ->get();
    }

    public function listActifsForOwner(User $user): Collection
    {
        return Vetement::query()
            ->where('user_id', (string) $user->getKey())
            ->whereNotIn('status', Vetement::STATUTS_HISTORIQUE)
            ->with('cycleEvents')
            ->orderByDesc('created_at')
            ->get();
    }

    public function listHistoriqueForOwner(User $user, ?string $statut = null): Collection
    {
        $query = Vetement::query()
            ->where('user_id', (string) $user->getKey())
            ->whereIn('status', Vetement::STATUTS_HISTORIQUE)
            ->with('cycleEvents')
            ->orderByDesc('updated_at');

        if (Vetement::estStatutHistorique($statut)) {
            $query->where('status', $statut);
        }

        return $query->get();
    }

    public function listAllForBackOffice(int $limit = 50): Collection
    {
        return Vetement::query()
            ->with(['owner', 'cycleEvents'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function listForAtelier(string $atelierId, int $limit = 50): Collection
    {
        return Vetement::query()
            ->where('atelier_id', $atelierId)
            ->with(['owner', 'cycleEvents'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    public function countsForAtelier(string $atelierId): array
    {
        $parStatut = Vetement::query()
            ->where('atelier_id', $atelierId)
            ->get(['status'])
            ->countBy('status');

        $counts = [];
        foreach (Vetement::STATUSES as $statut) {
            $counts[$statut] = (int) ($parStatut[$statut] ?? 0);
        }
        $counts['total'] = array_sum($counts);

        return $counts;
    }

    public function assignerPourReparation(Vetement $vetement, string $atelierId): Vetement
    {
        $vetement->atelier_id = $atelierId;
        $vetement->intended_action = Vetement::ACTION_REPARATION;
        $vetement->save();

        $this->recordCycleEvent($vetement, [
            'step_key' => CycleVieEvent::STEP_ACTION,
            'step_order' => $vetement->cycleEvents()->count() + 1,
            'title' => 'Rendez-vous demandé',
            'description' => 'La pièce a été transmise à un atelier pour réparation.',
            'status_snapshot' => $vetement->status,
            'occurred_at' => now(),
        ]);

        return $vetement->fresh(['cycleEvents', 'owner']) ?? $vetement;
    }

    public function synchroniserDepuisRendezVous(Vetement $vetement, string $statutRdv): Vetement
    {
        return match ($statutRdv) {
            \App\Modules\RendezVous\Models\RendezVous::STATUT_CONFIRME => $this->updateStatus(
                $vetement,
                Vetement::STATUS_EN_REPARATION,
                'Réparation en cours',
                'L\'atelier a confirmé le rendez-vous et prend en charge la pièce.'
            ),
            \App\Modules\RendezVous\Models\RendezVous::STATUT_TERMINE => $this->updateStatus(
                $vetement,
                Vetement::STATUS_REPARE,
                'Réparé',
                'L\'atelier a terminé la réparation.'
            ),
            \App\Modules\RendezVous\Models\RendezVous::STATUT_ANNULE => $this->remettreEnAttenteSiPossible($vetement),
            default => $vetement,
        };
    }

    public function traiterPourAtelier(string $vetementId, string $atelierId, string $statut): Vetement
    {
        $vetement = Vetement::query()->findOrFail($vetementId);

        if (! $vetement->appartientALatelier($atelierId)) {
            abort(403, 'Cette pièce n\'est pas affectée à votre atelier.');
        }

        if (! in_array($statut, $vetement->traitementsAtelier(), true)) {
            abort(422, 'Ce traitement n\'est pas possible pour le statut actuel de la pièce.');
        }

        $vetement = match ($statut) {
            Vetement::STATUS_EN_REPARATION => $this->updateStatus(
                $vetement,
                Vetement::STATUS_EN_REPARATION,
                'Réparation en cours',
                'L\'atelier a pris en charge cette pièce.'
            ),
            Vetement::STATUS_REPARE => $this->updateStatus(
                $vetement,
                Vetement::STATUS_REPARE,
                'Réparé',
                'La réparation est terminée.'
            ),
            default => $vetement,
        };

        $rdvStatut = $statut === Vetement::STATUS_REPARE
            ? \App\Modules\RendezVous\Models\RendezVous::STATUT_TERMINE
            : \App\Modules\RendezVous\Models\RendezVous::STATUT_CONFIRME;

        \App\Modules\RendezVous\Models\RendezVous::query()
            ->where('vetement_id', (string) $vetement->getKey())
            ->where('atelier_id', $atelierId)
            ->whereIn('statut', [
                \App\Modules\RendezVous\Models\RendezVous::STATUT_EN_ATTENTE,
                \App\Modules\RendezVous\Models\RendezVous::STATUT_CONFIRME,
            ])
            ->update(['statut' => $rdvStatut]);

        return $vetement;
    }

    private function remettreEnAttenteSiPossible(Vetement $vetement): Vetement
    {
        if ($vetement->status === Vetement::STATUS_REPARE) {
            return $vetement;
        }

        $vetement->status = Vetement::STATUS_EN_ATTENTE;
        $vetement->save();

        $this->recordCycleEvent($vetement, [
            'step_key' => CycleVieEvent::STEP_ACTION,
            'step_order' => $vetement->cycleEvents()->count() + 1,
            'title' => 'Rendez-vous annulé',
            'description' => 'Le rendez-vous a été annulé. La pièce redevient disponible.',
            'status_snapshot' => $vetement->status,
            'occurred_at' => now(),
        ]);

        return $vetement->fresh(['cycleEvents', 'owner']) ?? $vetement;
    }

    public function declare(User $user, array $data): Vetement
    {
        $vetement = Vetement::create([
            'user_id' => (string) $user->getKey(),
            'type' => $data['type'],
            'size' => $data['size'],
            'condition_label' => $data['condition_label'] ?? $data['condition'] ?? null,
            'material' => $data['material'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => Vetement::STATUS_EN_ATTENTE,
            'image_url' => $data['image_url'] ?? null,
            'image_path' => $data['image_path'] ?? null,
            'intended_action' => $data['intended_action'] ?? null,
        ]);

        $this->recordCycleEvent($vetement, [
            'step_key' => CycleVieEvent::STEP_DECLARE,
            'step_order' => 1,
            'title' => 'Déclaré',
            'description' => 'Vêtement ajouté à votre espace TexTileCycle.',
            'status_snapshot' => $vetement->status,
            'occurred_at' => now(),
        ]);

        if (! empty($data['intended_action'])) {
            $this->applyIntendedAction($vetement, $data['intended_action'], false);
        }

        return $vetement->load('cycleEvents');
    }

    public function applyIntendedAction(Vetement $vetement, string $action, bool $replaceExisting = true): Vetement
    {
        if (! in_array($action, Vetement::INTENDED_ACTIONS, true)) {
            return $vetement;
        }

        $vetement->intended_action = $action;
        $vetement->save();

        if ($replaceExisting) {
            CycleVieEvent::where('vetement_id', (string) $vetement->getKey())
                ->where('step_order', 2)
                ->delete();
        }

        $isDon = $action === Vetement::ACTION_DON;

        $this->recordCycleEvent($vetement, [
            'step_key' => CycleVieEvent::STEP_ANALYSE,
            'step_order' => 2,
            'title' => $isDon ? 'Parcours don' : 'Parcours réparation',
            'description' => $isDon
                ? 'Vous souhaitez offrir cette pièce à une association.'
                : 'Vous souhaitez faire réparer cette pièce en atelier.',
            'status_snapshot' => $vetement->status,
            'occurred_at' => now(),
        ]);

        return $vetement->fresh(['cycleEvents']);
    }

    public function findOwnedByUser(string $vetementId, User $user): ?Vetement
    {
        return Vetement::query()
            ->where('_id', $vetementId)
            ->where('user_id', (string) $user->getKey())
            ->first();
    }

    public function recordCycleEvent(Vetement $vetement, array $data): CycleVieEvent
    {
        return CycleVieEvent::create([
            'vetement_id' => (string) $vetement->getKey(),
            'step_key' => $data['step_key'],
            'step_order' => $data['step_order'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status_snapshot' => $data['status_snapshot'] ?? $vetement->status,
            'occurred_at' => $data['occurred_at'] ?? now(),
        ]);
    }

    public function updateStatus(Vetement $vetement, string $status, string $eventTitle, ?string $description = null): Vetement
    {
        $vetement->status = $status;
        $vetement->save();

        $order = $vetement->cycleEvents()->count() + 1;

        $this->recordCycleEvent($vetement, [
            'step_key' => Vetement::estStatutHistorique($status)
                ? CycleVieEvent::STEP_TERMINE
                : CycleVieEvent::STEP_ACTION,
            'step_order' => $order,
            'title' => $eventTitle,
            'description' => $description,
            'status_snapshot' => $status,
            'occurred_at' => now(),
        ]);

        return $vetement->fresh(['cycleEvents']);
    }

    public function enregistrerPropositionDon(Vetement $vetement, ?string $associationNom = null): Vetement
    {
        $vetement->intended_action = Vetement::ACTION_DON;
        $vetement->save();

        $this->recordCycleEvent($vetement, [
            'step_key' => CycleVieEvent::STEP_ACTION,
            'step_order' => $vetement->cycleEvents()->count() + 1,
            'title' => 'Don proposé',
            'description' => $associationNom
                ? 'Proposition envoyée à '.$associationNom.'.'
                : 'Une proposition de don a été envoyée.',
            'status_snapshot' => $vetement->status,
            'occurred_at' => now(),
        ]);

        return $vetement->fresh(['cycleEvents']) ?? $vetement;
    }

    public function enregistrerRefusDon(Vetement $vetement, ?string $associationNom = null): Vetement
    {
        $this->recordCycleEvent($vetement, [
            'step_key' => CycleVieEvent::STEP_ACTION,
            'step_order' => $vetement->cycleEvents()->count() + 1,
            'title' => 'Don refusé',
            'description' => $associationNom
                ? $associationNom.' n\'a pas pu accepter ce don.'
                : 'La proposition de don n\'a pas été retenue.',
            'status_snapshot' => $vetement->status,
            'occurred_at' => now(),
        ]);

        return $vetement->fresh(['cycleEvents']) ?? $vetement;
    }

    public function cloturerParDon(Vetement $vetement, ?string $associationNom = null): Vetement
    {
        $description = $associationNom
            ? 'L\'association '.$associationNom.' a accepté votre don.'
            : 'Votre don a été accepté.';

        return $this->updateStatus($vetement, Vetement::STATUS_DONNE, 'Donné', $description);
    }
}
