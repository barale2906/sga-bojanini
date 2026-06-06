<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Application\DTOs\PatientProcedureRecordData;
use App\Modules\CostCenter\Domain\Entities\PatientProcedureRecord;
use App\Modules\CostCenter\Domain\Repositories\MedicalServiceRepositoryInterface;
use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;

class CreatePatientProcedureRecordUseCase
{
    public function __construct(
        private readonly PatientProcedureRecordRepositoryInterface $repository,
        private readonly MedicalServiceRepositoryInterface $serviceRepository,
    ) {}

    public function execute(PatientProcedureRecordData $data): PatientProcedureRecord
    {
        $procedure = $this->serviceRepository->findById($data->medicalServiceId);

        if ($procedure === null) {
            throw new \DomainException("Procedimiento con id {$data->medicalServiceId} no encontrado.");
        }

        if (! $procedure->isProcedure()) {
            throw new \DomainException("El registro '{$procedure->getName()}' es un servicio, no un procedimiento. Los registros de paciente solo aplican a procedimientos.");
        }

        if ($data->quantity <= 0) {
            throw new \DomainException('La cantidad debe ser mayor a cero.');
        }

        if ($data->unitPrice < 0) {
            throw new \DomainException('El precio unitario no puede ser negativo.');
        }

        $record = PatientProcedureRecord::create(
            medicalServiceId:  $data->medicalServiceId,
            patientExternalId: $data->patientExternalId,
            patientDocument:   $data->patientDocument,
            patientFirstName:  $data->patientFirstName,
            patientLastName:   $data->patientLastName,
            quantity:          $data->quantity,
            unitPrice:         $data->unitPrice,
            serviceDate:       $data->serviceDate,
            notes:             $data->notes,
        );

        return $this->repository->save($record);
    }
}
