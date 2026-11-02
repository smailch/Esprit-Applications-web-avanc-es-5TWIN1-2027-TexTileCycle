<?php

namespace App\Modules\Associations\Services;

use App\Modules\Associations\Models\Association;
use Illuminate\Validation\ValidationException;

class AssociationService
{
    public function findOwnedByUser(string $userId): ?Association
    {
        return Association::query()->where('user_id', $userId)->first();
    }

    public function createOwn(string $userId, array $data): Association
    {
        if ($this->findOwnedByUser($userId)) {
            throw ValidationException::withMessages([
                'nom' => 'Votre fiche association existe déjà. Vous pouvez la modifier depuis votre espace.',
            ]);
        }

        unset($data['statut'], $data['user_id']);

        return Association::create(array_merge($data, [
            'user_id' => $userId,
            'statut' => Association::STATUT_EN_ATTENTE,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function normaliserBesoins(array $besoins): array
    {
        return array_values(array_map(function ($besoin) {
            if (isset($besoin['quantite']) && $besoin['quantite'] !== '') {
                $besoin['quantite'] = (int) $besoin['quantite'];
            }

            return $besoin;
        }, array_filter($besoins)));
    }
}
