<?php

namespace App\Modules\Core\Support;

use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use Throwable;

/**
 * Accès bas niveau aux collections MongoDB partagées entre modules.
 *
 * Permet aux modules transverses (administration, statistiques) de lire
 * les données des autres modules sans dépendre de leurs modèles Eloquent.
 */
class MongoCollections
{
    public static function get(string $name): Collection
    {
        return DB::connection('mongodb')->getMongoDB()->selectCollection($name);
    }

    public static function objectId(mixed $id): ?ObjectId
    {
        if ($id instanceof ObjectId) {
            return $id;
        }

        try {
            return new ObjectId((string) $id);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Filtre "_id" acceptant indifféremment un ObjectId ou un identifiant texte.
     */
    public static function idFilter(mixed $id): array
    {
        $oid = self::objectId($id);

        return $oid ? ['_id' => ['$in' => [$oid, (string) $id]]] : ['_id' => (string) $id];
    }

    public static function findById(string $collection, mixed $id, array $options = []): ?array
    {
        $doc = self::get($collection)->findOne(self::idFilter($id), $options + self::arrayTypeMap());

        return $doc ?: null;
    }

    public static function toDate(mixed $value): ?\Carbon\Carbon
    {
        return match (true) {
            $value instanceof UTCDateTime => \Carbon\Carbon::instance($value->toDateTime()),
            $value instanceof \DateTimeInterface => \Carbon\Carbon::instance($value),
            is_string($value) && $value !== '' => rescue(fn () => \Carbon\Carbon::parse($value), null, false),
            default => null,
        };
    }

    public static function arrayTypeMap(): array
    {
        return ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']];
    }
}
