<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;

class ListPendingDiscountOrdersUseCase
{
    public function __construct(
        private readonly PatientProcedureRecordRepositoryInterface $repo,
    ) {}

    public function execute(array $filters = []): array
    {
        return $this->repo->findPendingDiscountOrders($filters);
    }
}
