<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Entities\PatientProcedureRecord;
use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;

class GetServiceOrderUseCase
{
    public function __construct(
        private readonly PatientProcedureRecordRepositoryInterface $repo,
    ) {}

    /** @return PatientProcedureRecord[] */
    public function execute(string $orderNumber): array
    {
        $records = $this->repo->findByOrderNumber($orderNumber);

        if (empty($records)) {
            throw new \DomainException("Orden {$orderNumber} no encontrada.");
        }

        return $records;
    }
}
