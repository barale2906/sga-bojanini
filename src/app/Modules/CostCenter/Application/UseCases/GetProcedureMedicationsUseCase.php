<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;

/**
 * Obtiene los medicamentos (clasificación MED) despachados en el movimiento
 * de inventario vinculado a un registro de procedimiento de paciente.
 *
 * La fuente de verdad es stock_movements, no un registro manual.
 * Si el registro no tiene movement_document_id devuelve array vacío.
 */
class GetProcedureMedicationsUseCase
{
    public function __construct(
        private readonly PatientProcedureRecordRepositoryInterface $recordRepository,
    ) {}

    public function execute(int $patientProcedureRecordId): array
    {
        $record = $this->recordRepository->findById($patientProcedureRecordId);

        if ($record === null) {
            throw new \DomainException("Registro de procedimiento con id {$patientProcedureRecordId} no encontrado.");
        }

        if ($record->getMovementDocumentId() === null) {
            return [];
        }

        return StockMovementModel::with([
            'variant.genericProduct.classification',
            'batch',
        ])
            ->where('movement_document_id', $record->getMovementDocumentId())
            ->get()
            ->filter(fn ($m) => $m->variant?->genericProduct?->classification?->code === 'MED')
            ->map(fn ($m) => [
                'generic_product_id' => $m->variant?->generic_product_id,
                'product_name'       => $m->variant?->genericProduct?->name,
                'batch_id'           => $m->batch_id,
                'lot_number'         => $m->batch?->lot_number,
                'expiration_date'    => $m->batch?->expiration_date?->format('Y-m-d'),
                'quantity'           => abs((float) $m->quantity),
            ])
            ->values()
            ->toArray();
    }
}
