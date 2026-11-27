<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Application\Services\MovementTimestamp;
use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Services\BatchLocationService;
use App\Modules\Inventory\Domain\Services\DocumentNumberGenerator;
use App\Modules\Inventory\Domain\ValueObjects\MovementStatus;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Facades\DB;

/**
 * Devuelve al inventario lo que sacó un documento de salida (p. ej. los insumos
 * de una orden de servicio anulada): cada línea vuelve al mismo lote y a la misma
 * ubicación de donde salió, mediante un ajuste positivo ya confirmado (sin firma:
 * es una corrección del sistema, no un conteo). Debe ejecutarse dentro de una
 * transacción; no se puede aplicar dos veces al mismo documento.
 */
class ReverseExitDocumentUseCase
{
    public const REFERENCE_TYPE = 'movement_document_reversal';

    public function __construct(
        private readonly BatchLocationService $batchLocationService,
        private readonly DocumentNumberGenerator $numberGenerator,
    ) {}

    /**
     * @return MovementDocumentModel|null el ajuste creado, o null si el documento no descontó stock
     */
    public function execute(int $exitDocumentId, string $reason, int $userId): ?MovementDocumentModel
    {
        $lines = StockMovementModel::where('movement_document_id', $exitDocumentId)
            ->where('movement_type', 'exit')
            ->where('status', MovementStatus::CONFIRMED->value)
            ->whereNotNull('batch_id')
            ->lockForUpdate()
            ->get();

        if ($lines->isEmpty()) {
            return null;
        }

        $this->assertNotReversed($exitDocumentId);

        $warehouseId = (int) $lines->first()->warehouse_id;
        $document    = MovementDocumentModel::create([
            'document_number' => $this->numberGenerator->next('adjustment'),
            'document_type'   => 'adjustment',
            'warehouse_id'    => $warehouseId,
            'movement_date'   => MovementTimestamp::resolve(null),
            'reason'          => $reason,
            'user_id'         => $userId,
            'status'          => MovementStatus::CONFIRMED->value,
        ]);

        foreach ($lines as $line) {
            $this->restoreLine($line, $document, $reason, $userId);
        }

        return $document;
    }

    private function restoreLine(StockMovementModel $line, MovementDocumentModel $document, string $reason, int $userId): void
    {
        $quantity    = abs((float) $line->quantity);
        $warehouseId = (int) $line->warehouse_id;
        $locationId  = $line->location_from_id ?? $this->defaultLocation($warehouseId);

        $this->batchLocationService->deposit((int) $line->batch_id, $warehouseId, $locationId, $quantity);

        $movement = StockMovementModel::create([
            'movement_document_id' => $document->id,
            'warehouse_id'         => $warehouseId,
            'product_variant_id'   => $line->product_variant_id,
            'batch_id'             => $line->batch_id,
            'location_to_id'       => $locationId,
            'movement_type'        => 'adjustment',
            'quantity'             => $quantity,
            'reason'               => $reason,
            'movement_date'        => $document->movement_date,
            'reference_type'       => self::REFERENCE_TYPE,
            'reference_id'         => $line->movement_document_id,
            'user_id'              => $userId,
            'status'               => MovementStatus::CONFIRMED->value,
        ]);

        event(new StockMovementCreated(
            movementId:       $movement->id,
            productVariantId: (int) $line->product_variant_id,
            warehouseId:      $warehouseId,
            movementType:     'adjustment',
            quantity:         $quantity,
        ));
    }

    private function assertNotReversed(int $exitDocumentId): void
    {
        $alreadyReversed = StockMovementModel::where('reference_type', self::REFERENCE_TYPE)
            ->where('reference_id', $exitDocumentId)
            ->exists();

        if ($alreadyReversed) {
            throw new \DomainException('Los insumos de este documento ya fueron devueltos al inventario.');
        }
    }

    /** Lotes antiguos sin ubicación registrada: se devuelven a la primera ubicación del almacén. */
    private function defaultLocation(int $warehouseId): int
    {
        $locationId = DB::table('locations')
            ->join('zones', 'locations.zone_id', '=', 'zones.id')
            ->where('zones.warehouse_id', $warehouseId)
            ->orderBy('locations.id')
            ->value('locations.id');

        return $locationId ?? throw new \DomainException('El almacén no tiene ubicaciones para devolver los insumos.');
    }
}
