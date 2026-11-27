<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Entities\PatientProcedureRecord;
use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;
use App\Modules\Inventory\Application\UseCases\ReverseExitDocumentUseCase;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

class UpdateOrderBillingStatusUseCase
{
    private const ALLOWED = ['billed', 'cancelled'];

    public function __construct(
        private readonly PatientProcedureRecordRepositoryInterface $repo,
        private readonly ReverseExitDocumentUseCase $reverseExit,
    ) {}

    /**
     * Facturar o anular una orden.
     *
     * Una orden facturada no se puede anular: se factura cuando el paciente ya
     * fue atendido y los insumos registrados son los que se usaron. Al anular una
     * orden sin facturar, sus insumos vuelven al inventario en la misma operación.
     *
     * @param  string $status 'billed' | 'cancelled'
     * @return PatientProcedureRecord[]
     */
    public function execute(string $orderNumber, string $status, int $userId): array
    {
        if (! in_array($status, self::ALLOWED, true)) {
            throw new \DomainException("Estado de facturación inválido: {$status}.");
        }

        return DB::transaction(function () use ($orderNumber, $status, $userId) {
            $records = $this->repo->findByOrderNumber($orderNumber, lockForUpdate: true);

            if (empty($records)) {
                throw new \DomainException("Orden {$orderNumber} no encontrada.");
            }

            $this->assertTransitionAllowed($orderNumber, $records[0]->getBillingStatus(), $status);

            if ($status === 'cancelled') {
                $this->returnSuppliesToInventory($orderNumber, $records, $userId);
            }

            $this->repo->updateBillingStatusForOrder($orderNumber, $status, $userId, new DateTimeImmutable());

            return $this->repo->findByOrderNumber($orderNumber);
        });
    }

    private function assertTransitionAllowed(string $orderNumber, ?string $current, string $status): void
    {
        if ($current === $status) {
            throw new \DomainException("La orden {$orderNumber} ya se encuentra en estado '{$status}'.");
        }

        if ($status === 'billed' && $current === 'cancelled') {
            throw new \DomainException("La orden {$orderNumber} está anulada y no puede ser facturada.");
        }

        if ($status === 'cancelled' && $current === 'billed') {
            throw new \DomainException("La orden {$orderNumber} ya está facturada y no se puede anular: los insumos registrados corresponden a la atención del paciente.");
        }
    }

    /** @param PatientProcedureRecord[] $records */
    private function returnSuppliesToInventory(string $orderNumber, array $records, int $userId): void
    {
        $documentIds = collect($records)
            ->map(fn (PatientProcedureRecord $record) => $record->getMovementDocumentId())
            ->filter()
            ->unique();

        foreach ($documentIds as $documentId) {
            $this->reverseExit->execute((int) $documentId, "Anulación de la orden {$orderNumber}", $userId);
        }
    }
}
