<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Domain\Repositories\AssetSiteRepositoryInterface;

class DeleteAssetSiteUseCase
{
    public function __construct(
        private readonly AssetSiteRepositoryInterface $repository,
    ) {}

    public function execute(int $id): void
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException("Sede con id {$id} no encontrada.");
        }

        if ($this->repository->hasPlaces($id)) {
            throw new \DomainException('No se puede eliminar la sede porque tiene sitios. Elimínelos o desactive la sede.');
        }

        $this->repository->delete($id);
    }
}
