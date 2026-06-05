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
        $service = $this->repository->findById($id);

        if ($service === null) {
            throw new \DomainException("Servicio médico con id {$id} no encontrado.");
        }

        $model = MedicalServiceModel::findOrFail($id);

        if ($model->stockMovements()->exists()) {
            throw new \DomainException('No se puede eliminar el servicio porque tiene movimientos de inventario asociados.');
        }

        if ($service->isService() && $model->children()->exists()) {
            throw new \DomainException('No se puede eliminar el servicio porque tiene procedimientos asociados. Elimine primero los procedimientos.');
        }

        if ($service->isProcedure()) {
            if ($model->procedurePrices()->exists()) {
                throw new \DomainException('No se puede eliminar el procedimiento porque tiene tarifas registradas. Elimine primero las tarifas.');
            }

            if ($model->patientProcedureRecords()->exists()) {
                throw new \DomainException('No se puede eliminar el procedimiento porque tiene registros de pacientes asociados.');
            }
        }

        $this->repository->delete($id);
    }
}
