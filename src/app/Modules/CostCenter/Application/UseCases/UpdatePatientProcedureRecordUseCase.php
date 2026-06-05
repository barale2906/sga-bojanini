<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Application\DTOs\PatientProcedureRecordData;
use App\Modules\CostCenter\Domain\Entities\PatientProcedureRecord;
use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;

class UpdatePatientProcedureRecordUseCase
{
    public function __construct(
        private readonly PatientProcedureRecordRepositoryInterface $repository,
    ) {}

    public function execute(int $id, PatientProcedureRecordData $data): PatientProcedureRecord
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException("Registro de procedimiento con id {$id} no encontrado.");
        }

        if ($data->quantity <= 0) {
            throw new \DomainException('La cantidad debe ser mayor a cero.');
        }

        if ($data->unitPrice < 0) {
            throw new \DomainException('El precio unitario no puede ser negativo.');
        }

        $updated = new PatientProcedureRecord(
            id:                $id,
            medicalServiceId:  $data->medicalServiceId,
            patientExternalId: $data->patientExternalId,
            patientDocument:   $data->patientDocument,
            quantity:          $data->quantity,
            unitPrice:         $data->unitPrice,
            total:             PatientProcedureRecord::calculateTotal($data->quantity, $data->unitPrice),
            serviceDate:       $data->serviceDate,
            notes:             $data->notes,
            isActive:          $data->isActive,
        );

        return $this->repository->save($updated);
    }
}
