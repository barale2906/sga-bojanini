<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Repositories\MedicalServiceRepositoryInterface;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;

class DeleteMedicalServiceUseCase
{
    public function __construct(
        private readonly MedicalServiceRepositoryInterface $repository,
    ) {}

    public function execute(int $id): void
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException("Servicio médico con id {$id} no encontrado.");
        }

        if (MedicalServiceModel::findOrFail($id)->stockMovements()->exists()) {
            throw new \DomainException('No se puede eliminar el servicio porque tiene movimientos de inventario asociados.');
        }

        $this->repository->delete($id);
    }
}
