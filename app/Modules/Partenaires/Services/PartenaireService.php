<?php

namespace App\Modules\Partenaires\Services;

use App\Modules\Core\Support\MongoCollections;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use MongoDB\BSON\UTCDateTime;

/**
 * Workflow de validation / suspension des comptes partenaires.
 *
 * Opère directement sur les collections `ateliers` et `associations`
 * (modules 2 et 4) : seul le champ `statut` est modifié par l'administration.
 */
class PartenaireService
{
    public const STATUT_EN_ATTENTE = 'en_attente';

    public const STATUT_ACTIF = 'actif';

    public const STATUT_SUSPENDU = 'suspendu';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_ACTIF,
        self::STATUT_SUSPENDU,
    ];

    public const TYPES = [
        'atelier' => ['collection' => 'ateliers', 'label' => 'Atelier'],
        'association' => ['collection' => 'associations', 'label' => 'Association'],
    ];

    public function list(string $type, ?string $statut = null, ?string $search = null): Collection
    {
        $filter = [];

        if ($statut && in_array($statut, self::STATUTS, true)) {
            $filter['statut'] = $statut;
        }

        if ($search) {
            $regex = ['$regex' => preg_quote($search, '/'), '$options' => 'i'];
            $filter['$or'] = [['nom' => $regex], ['adresse' => $regex], ['ville' => $regex]];
        }

        $docs = MongoCollections::get($this->collection($type))
            ->find($filter, ['sort' => ['created_at' => -1]] + MongoCollections::arrayTypeMap())
            ->toArray();

        $owners = $this->owners(array_column($docs, 'user_id'));

        return collect($docs)->map(fn (array $doc) => [
            'id' => (string) $doc['_id'],
            'type' => $type,
            'nom' => $doc['nom'] ?? '—',
            'adresse' => trim(($doc['adresse'] ?? '').' '.($doc['ville'] ?? '')),
            'telephone' => $doc['telephone'] ?? null,
            'statut' => $doc['statut'] ?? self::STATUT_EN_ATTENTE,
            'note' => $doc['note_moyenne'] ?? null,
            'owner' => $owners[(string) ($doc['user_id'] ?? '')] ?? null,
            'created_at' => MongoCollections::toDate($doc['created_at'] ?? null),
        ]);
    }

    /**
     * @return array<string, int> nombre de partenaires par statut
     */
    public function countsByStatut(string $type): array
    {
        $counts = array_fill_keys(self::STATUTS, 0);

        $rows = MongoCollections::get($this->collection($type))->aggregate([
            ['$group' => ['_id' => '$statut', 'n' => ['$sum' => 1]]],
        ]);

        foreach ($rows as $row) {
            $key = $row['_id'] ?? self::STATUT_EN_ATTENTE;
            $counts[$key] = ($counts[$key] ?? 0) + $row['n'];
        }

        return $counts;
    }

    public function changerStatut(string $type, string $id, string $statut): bool
    {
        if (! in_array($statut, self::STATUTS, true)) {
            throw new InvalidArgumentException("Statut partenaire invalide : {$statut}");
        }

        $result = MongoCollections::get($this->collection($type))->updateOne(
            MongoCollections::idFilter($id),
            ['$set' => ['statut' => $statut, 'updated_at' => new UTCDateTime()]]
        );

        return $result->getMatchedCount() > 0;
    }

    public function collection(string $type): string
    {
        return self::TYPES[$type]['collection']
            ?? throw new InvalidArgumentException("Type de partenaire inconnu : {$type}");
    }

    /**
     * @return array<string, array{name: string, email: string}>
     */
    private function owners(array $userIds): array
    {
        $ids = array_values(array_filter(array_map(
            fn ($id) => MongoCollections::objectId($id),
            array_unique(array_map('strval', $userIds))
        )));

        if ($ids === []) {
            return [];
        }

        $users = MongoCollections::get('users')->find(
            ['_id' => ['$in' => $ids]],
            ['projection' => ['name' => 1, 'email' => 1]] + MongoCollections::arrayTypeMap()
        );

        $owners = [];
        foreach ($users as $user) {
            $owners[(string) $user['_id']] = ['name' => $user['name'] ?? '', 'email' => $user['email'] ?? ''];
        }

        return $owners;
    }
}
