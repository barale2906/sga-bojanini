<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Domain\Repositories;

use App\Modules\CostCenter\Domain\Entities\PatientClinicalEvolution;

interface PatientClinicalEvolutionRepositoryInterface
{
    public function findById(int $id): ?PatientClinicalEvolution;

    /** @return PatientClinicalEvolution[] */
    public function findByRecordId(int $patientProcedureRecordId): array;

    public function save(PatientClinicalEvolution $evolution): PatientClinicalEvolution;

    public function delete(int $id): void;
}
