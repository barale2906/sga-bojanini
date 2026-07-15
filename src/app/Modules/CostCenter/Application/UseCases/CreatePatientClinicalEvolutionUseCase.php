<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Application\DTOs\PatientClinicalEvolutionData;
use App\Modules\CostCenter\Domain\Entities\PatientClinicalEvolution;
use App\Modules\CostCenter\Domain\Repositories\PatientClinicalEvolutionRepositoryInterface;
use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;

class CreatePatientClinicalEvolutionUseCase
{
    public function __construct(
        private readonly PatientClinicalEvolutionRepositoryInterface $repository,
        private readonly PatientProcedureRecordRepositoryInterface $recordRepository,
    ) {}

    public function execute(PatientClinicalEvolutionData $data): PatientClinicalEvolution
    {
        $record = $this->recordRepository->findById($data->patientProcedureRecordId);

        if ($record === null) {
            throw new \DomainException("Registro de procedimiento con id {$data->patientProcedureRecordId} no encontrado.");
        }

        $evolution = PatientClinicalEvolution::create(
            patientProcedureRecordId: $data->patientProcedureRecordId,
            content:                  $data->content,
            userId:                   $data->userId,
            recordedAt:               $data->recordedAt,
        );

        return $this->repository->save($evolution);
    }
}
