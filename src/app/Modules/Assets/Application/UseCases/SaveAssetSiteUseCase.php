<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Application\DTOs\AssetSiteData;
use App\Modules\Assets\Domain\Entities\AssetSite;
use App\Modules\Assets\Domain\Repositories\AssetSiteRepositoryInterface;

/** Crea (id null) o actualiza una sede. */
class SaveAssetSiteUseCase
{
    public function __construct(
        private readonly AssetSiteRepositoryInterface $repository,
    ) {}

    public function execute(?int $id, AssetSiteData $data): AssetSite
    {
        $name = trim($data->name);

        if ($id !== null && $this->repository->findById($id) === null) {
            throw new \DomainException("Sede con id {$id} no encontrada.");
        }

        $conflict = $this->repository->findByName($name);

        if ($conflict !== null && $conflict->getId() !== $id) {
            throw new \DomainException("Ya existe una sede con el nombre '{$name}'.");
        }

        return $this->repository->save(new AssetSite($id, $name, $data->address, $data->isActive));
    }
}
