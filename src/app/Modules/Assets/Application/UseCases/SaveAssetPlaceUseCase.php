<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Application\DTOs\AssetPlaceData;
use App\Modules\Assets\Domain\Entities\AssetPlace;
use App\Modules\Assets\Domain\Repositories\AssetPlaceRepositoryInterface;
use App\Modules\Assets\Domain\Repositories\AssetSiteRepositoryInterface;

/** Crea (id null) o actualiza un sitio dentro de una sede. */
class SaveAssetPlaceUseCase
{
    public function __construct(
        private readonly AssetPlaceRepositoryInterface $repository,
        private readonly AssetSiteRepositoryInterface $sites,
    ) {}

    public function execute(?int $id, AssetPlaceData $data): AssetPlace
    {
        $name = trim($data->name);

        if ($this->sites->findById($data->siteId) === null) {
            throw new \DomainException("Sede con id {$data->siteId} no encontrada.");
        }

        if ($id !== null) {
            $existing = $this->repository->findById($id)
                ?? throw new \DomainException("Sitio con id {$id} no encontrado.");

            if ($existing->getSiteId() !== $data->siteId && $this->repository->isInUse($id)) {
                throw new \DomainException('No se puede mover a otra sede un sitio que ya tiene activos o historial; cree un sitio nuevo.');
            }
        }

        $conflict = $this->repository->findBySiteAndName($data->siteId, $name);

        if ($conflict !== null && $conflict->getId() !== $id) {
            throw new \DomainException("La sede ya tiene un sitio llamado '{$name}'.");
        }

        return $this->repository->save(new AssetPlace($id, $data->siteId, $name, $data->isActive));
    }
}
