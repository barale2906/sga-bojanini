<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Entities\PatientProcedureRecord;
use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;
use DateTimeImmutable;

class UpdateOrderBillingStatusUseCase
{
    private const ALLOWED = ['billed', 'cancelled'];

    public function __construct(
        private readonly PatientProcedureRecordRepositoryInterface $repo,
    ) {}

    /**
     * @param  string $status 'billed' | 'cancelled'
     * @return PatientProcedureRecord[]
     */
    public function execute(string $orderNumber, string $status, int $userId): array
    {
        if (! in_array($status, self::ALLOWED, true)) {
            throw new \DomainException("Estado de facturación inválido: {$status}.");
        }

        $records = $this->repo->findByOrderNumber($orderNumber);

        if (empty($records)) {
            throw new \DomainException("Orden {$orderNumber} no encontrada.");
        }

        $current = $records[0]->getBillingStatus();

        if ($current === $status) {
            throw new \DomainException("La orden {$orderNumber} ya se encuentra en estado '{$status}'.");
        }

        if ($status === 'billed' && $current === 'cancelled') {
            throw new \DomainException("La orden {$orderNumber} está anulada y no puede ser facturada.");
        }

        $this->repo->updateBillingStatusForOrder($orderNumber, $status, $userId, new DateTimeImmutable());

        return $this->repo->findByOrderNumber($orderNumber);
    }
}
