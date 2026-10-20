<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Domain\Repositories\AssetCategoryRepositoryInterface;

class DeleteAssetCategoryUseCase
{
    public function __construct(
        private readonly AssetCategoryRepositoryInterface $repository,
    ) {}

    public function execute(int $id): void
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException("Categoría de activos con id {$id} no encontrada.");
        }

        if ($this->repository->hasAssets($id)) {
            throw new \DomainException('No se puede eliminar la categoría porque tiene activos asociados. Desactívela en su lugar.');
        }

        $this->repository->delete($id);
    }
}
