<?php

namespace App\Modules\Ateliers\Services;

use App\Modules\Ateliers\Models\Atelier;
use App\Modules\Ateliers\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Catalogue de prestations d'un atelier. Toutes les requêtes sont scopées par atelier_id :
 * pour un atelier connecté, $atelier doit venir de AtelierService::findOwnedByUser(), jamais d'un id saisi.
 * Un service d'un autre atelier lève ModelNotFoundException (404, sans révéler son existence).
 */
class ServiceCatalogueService
{
    private const CHAMPS = ['nom', 'description', 'prix_estime', 'duree_estimee'];

    /**
     * @return Collection<int, Service>
     */
    public function list(Atelier $atelier): Collection
    {
        return $this->scope($atelier)->orderBy('created_at')->get();
    }

    public function find(Atelier $atelier, string $serviceId): Service
    {
        return $this->scope($atelier)->where('_id', $serviceId)->firstOrFail();
    }

    public function create(Atelier $atelier, array $data): Service
    {
        $service = new Service(array_intersect_key($data, array_flip(self::CHAMPS)));
        $service->atelier_id = (string) $atelier->getKey();
        $service->save();

        return $service;
    }

    public function update(Atelier $atelier, string $serviceId, array $data): Service
    {
        $service = $this->find($atelier, $serviceId);
        $service->fill(array_intersect_key($data, array_flip(self::CHAMPS)));
        $service->save();

        return $service;
    }

    public function delete(Atelier $atelier, string $serviceId): void
    {
        $this->find($atelier, $serviceId)->delete();
    }

    private function scope(Atelier $atelier): Builder
    {
        return Service::query()->where('atelier_id', (string) $atelier->getKey());
    }
}
