<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Repositories\CostCenterRepositoryInterface;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;

class DeleteCostCenterUseCase
{
    public function __construct(
        private readonly CostCenterRepositoryInterface $repository,
    ) {}

    public function execute(int $id): void
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException("Centro de costo con id {$id} no encontrado.");
        }

        if (CostCenterModel::findOrFail($id)->stockMovements()->exists()) {
            throw new \DomainException('No se puede eliminar el centro de costo porque tiene movimientos asociados.');
        }

        $this->repository->delete($id);
    }
}
