<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Repositories\PatientClinicalEvolutionRepositoryInterface;

class ListPatientClinicalEvolutionsUseCase
{
    public function __construct(
        private readonly PatientClinicalEvolutionRepositoryInterface $repository,
    ) {}

    public function execute(int $patientProcedureRecordId): array
    {
        return $this->repository->findByRecordId($patientProcedureRecordId);
    }
}
