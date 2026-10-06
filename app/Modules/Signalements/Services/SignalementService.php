<?php

namespace App\Modules\Signalements\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Core\Support\MongoCollections;
use App\Modules\Partenaires\Services\PartenaireService;
use App\Modules\Signalements\Models\Signalement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use MongoDB\BSON\UTCDateTime;

class SignalementService
{
    public function __construct(
        private PartenaireService $partenaires
    ) {
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Signalement::query()
            ->with('auteur')
            ->when($filters['statut'] ?? null, fn ($q, $statut) => $q->where('statut', $statut))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['cible_type'] ?? null, fn ($q, $cible) => $q->where('cible_type', $cible))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, int>
     */
    public function countsByStatut(): array
    {
        $counts = array_fill_keys(Signalement::STATUTS, 0);

        foreach (Signalement::STATUTS as $statut) {
            $counts[$statut] = Signalement::where('statut', $statut)->count();
        }

        return $counts;
    }

    public function cibleExiste(string $cibleType, string $cibleId): bool
    {
        $config = Signalement::CIBLES[$cibleType] ?? null;

        return $config !== null && MongoCollections::findById($config['collection'], $cibleId, ['projection' => ['_id' => 1]]) !== null;
    }

    public function create(array $data, User $auteur): Signalement
    {
        return Signalement::create([
            'user_id' => (string) $auteur->getKey(),
            'type' => $data['type'] ?? Signalement::CIBLES[$data['cible_type']]['type'],
            'cible_type' => $data['cible_type'],
            'cible_id' => (string) $data['cible_id'],
            'motif' => $data['motif'],
            'statut' => Signalement::STATUT_EN_ATTENTE,
        ]);
    }

    public function update(Signalement $signalement, array $data): Signalement
    {
        $signalement->fill(array_intersect_key($data, array_flip(['type', 'motif', 'note_admin'])));
        $signalement->save();

        return $signalement;
    }

    /**
     * Traite ou rejette un signalement, avec sanction optionnelle de la cible.
     */
    public function moderer(Signalement $signalement, string $statut, User $admin, ?string $note = null, bool $sanctionner = false): ?string
    {
        $signalement->fill([
            'statut' => $statut,
            'note_admin' => $note,
            'traite_par' => (string) $admin->getKey(),
            'traite_le' => now(),
        ])->save();

        if ($statut !== Signalement::STATUT_TRAITE || ! $sanctionner) {
            return null;
        }

        return $this->sanctionner($signalement, $admin);
    }

    public function delete(Signalement $signalement): void
    {
        $signalement->delete();
    }

    /**
     * Applique la sanction adaptée au type de cible et renvoie sa description.
     */
    private function sanctionner(Signalement $signalement, User $admin): ?string
    {
        return match ($signalement->cible_type) {
            'Atelier' => $this->partenaires->changerStatut('atelier', $signalement->cible_id, PartenaireService::STATUT_SUSPENDU)
                ? 'Atelier suspendu.' : null,
            'Association' => $this->partenaires->changerStatut('association', $signalement->cible_id, PartenaireService::STATUT_SUSPENDU)
                ? 'Association suspendue.' : null,
            'User' => $this->desactiverUtilisateur($signalement->cible_id, $admin),
            default => null,
        };
    }

    private function desactiverUtilisateur(string $userId, User $admin): ?string
    {
        if ($userId === (string) $admin->getKey()) {
            return null;
        }

        $result = MongoCollections::get('users')->updateOne(
            MongoCollections::idFilter($userId) + ['role' => ['$ne' => User::ROLE_ADMIN]],
            ['$set' => ['is_active' => false, 'updated_at' => new UTCDateTime()]]
        );

        return $result->getModifiedCount() > 0 ? 'Compte utilisateur désactivé.' : null;
    }
}
