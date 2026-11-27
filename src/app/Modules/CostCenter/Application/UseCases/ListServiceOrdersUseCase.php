<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;

class ListServiceOrdersUseCase
{
    public function __construct(
        private readonly PatientProcedureRecordRepositoryInterface $repo,
    ) {}

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function execute(array $filters, int $perPage, int $page): array
    {
        return $this->repo->paginateOrders($filters, $perPage, $page);
    }
}
