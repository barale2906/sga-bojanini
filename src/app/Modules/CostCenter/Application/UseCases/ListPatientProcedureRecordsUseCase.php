<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;

class ListPatientProcedureRecordsUseCase
{
    public function __construct(
        private readonly PatientProcedureRecordRepositoryInterface $repository,
    ) {}

    public function execute(array $filters = []): array
    {
        return $this->repository->findAll($filters);
    }
}
