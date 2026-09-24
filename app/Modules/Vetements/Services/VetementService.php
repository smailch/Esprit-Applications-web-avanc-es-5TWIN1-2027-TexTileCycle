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

    public function listAllForBackOffice(int $limit = 50): Collection
    {
        return Vetement::query()
            ->with(['owner', 'cycleEvents'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
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

        $order = min($vetement->cycleEvents()->count() + 1, 4);

        $this->recordCycleEvent($vetement, [
            'step_key' => CycleVieEvent::STEP_ACTION,
            'step_order' => $order,
            'title' => $eventTitle,
            'description' => $description,
            'status_snapshot' => $status,
            'occurred_at' => now(),
        ]);

        return $vetement->fresh(['cycleEvents']);
    }
}
