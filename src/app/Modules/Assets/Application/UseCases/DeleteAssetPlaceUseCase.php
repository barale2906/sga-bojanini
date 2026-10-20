<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Domain\Repositories\AssetPlaceRepositoryInterface;

class DeleteAssetPlaceUseCase
{
    public function __construct(
        private readonly AssetPlaceRepositoryInterface $repository,
    ) {}

    public function execute(int $id): void
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException("Sitio con id {$id} no encontrado.");
        }

        if ($this->repository->isInUse($id)) {
            throw new \DomainException('No se puede eliminar el sitio porque tiene activos o historial de ubicaciones. Desactívelo en su lugar.');
        }

        $this->repository->delete($id);
    }
}
