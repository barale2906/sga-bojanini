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

    /**
     * @return array{items: array, total: int}
     */
    public function paginate(array $filters, int $perPage, int $page): array
    {
        return $this->repository->paginateAll($filters, $perPage, $page);
    }
}
