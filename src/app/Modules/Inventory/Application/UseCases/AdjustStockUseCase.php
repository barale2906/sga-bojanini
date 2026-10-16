<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Services\BatchLocationService;
use App\Modules\Inventory\Domain\Services\DocumentNumberGenerator;
use App\Modules\Inventory\Domain\Services\FEFOService;
use App\Modules\Inventory\Domain\ValueObjects\MovementStatus;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Facades\DB;

class AdjustStockUseCase
{
    public function __construct(
        private readonly FEFOService $fefoService,
        private readonly BatchLocationService $batchLocationService,
        private readonly DocumentNumberGenerator $numberGenerator,
    ) {}

    public function execute(array $data): StockMovementModel
    {
        return $this->createPending($data);
    }

    /**
     * Valida la disponibilidad y crea el documento + registro pendiente de firma.
     * Para ajustes negativos verifica el stock del lote en el almacén (se admiten
     * lotes vencidos, p. ej. descartes por conteo físico).
     * Para ajustes positivos verifica que exista un lote vigente.
     */
    public function createPending(array $data): StockMovementModel
    {
        return DB::transaction(function () use ($data) {
            $quantity    = (float) $data['quantity'];
            $warehouseId = (int) $data['warehouse_id'];

            if ($quantity == 0) {
                throw new \DomainException('La cantidad de ajuste no puede ser cero.');
            }

            if (! empty($data['location_id'])) {
                $this->batchLocationService->assertLocationInWarehouse((int) $data['location_id'], $warehouseId);
            }

            $batchId = $this->resolveBatchId($data, $quantity, $warehouseId);

            $document = MovementDocumentModel::create([
                'document_number' => $this->numberGenerator->next('adjustment'),
                'document_type'   => 'adjustment',
                'warehouse_id'    => $warehouseId,
                'reason'          => $data['reason'],
                'user_id'         => $data['user_id'],
                'status'          => MovementStatus::PENDING_SIGNATURE->value,
            ]);

            return StockMovementModel::create([
                'movement_document_id' => $document->id,
                'warehouse_id'         => $warehouseId,
                'product_variant_id'   => $data['product_variant_id'],
                'batch_id'             => $batchId,
                'location_from_id'     => $quantity < 0 ? ($data['location_id'] ?? null) : null,
                'location_to_id'       => $quantity > 0 ? ($data['location_id'] ?? null) : null,
                'movement_type'        => 'adjustment',
                'quantity'             => $quantity,
                'reason'               => $data['reason'],
                'user_id'              => $data['user_id'],
                'status'               => MovementStatus::PENDING_SIGNATURE->value,
            ]);
        });
    }

    private function resolveBatchId(array $data, float $quantity, int $warehouseId): int
    {
        if (! empty($data['batch_id'])) {
            $batch = BatchModel::findOrFail((int) $data['batch_id']);

            if ((int) $batch->product_variant_id !== (int) $data['product_variant_id']) {
                throw new \DomainException(
                    "El lote '{$batch->lot_number}' no corresponde a la variante indicada."
                );
            }

            if ($quantity < 0) {
                $this->fefoService->selectSpecificBatch($batch, $warehouseId, abs($quantity), includeExpired: true);
            }

            return $batch->id;
        }

        if ($quantity < 0) {
            // Los ajustes negativos también gestionan stock vencido (p. ej.
            // descartes por conteo físico), por lo que se incluyen lotes con
            // expiration_date pasada.
            $selectedBatches = $this->fefoService->selectBatchesForExit(
                $data['product_variant_id'],
                $warehouseId,
                abs($quantity),
                includeExpired: true,
            );

            return $selectedBatches[0]['batch_id'];
        }

        $batch = BatchModel::where('product_variant_id', $data['product_variant_id'])
            ->valid()
            ->orderBy('expiration_date')
            ->first();

        if ($batch === null) {
            throw new \DomainException('No hay lotes vigentes para ajustar.');
        }

        return $batch->id;
    }

    public function applyStock(StockMovementModel $movement): void
    {
        DB::transaction(function () use ($movement) {
            if ($movement->quantity > 0) {
                $this->applyPositiveAdjustment($movement);
            } else {
                $this->applyNegativeAdjustment($movement);
            }
        });
    }

    private function applyPositiveAdjustment(StockMovementModel $movement): void
    {
        $quantity = (float) $movement->quantity;

        if ($movement->location_to_id === null) {
            throw new \DomainException('El ajuste positivo requiere la ubicación de destino.');
        }

        BatchModel::whereKey($movement->batch_id)->increment('quantity_received', $quantity);

        $this->batchLocationService->deposit(
            $movement->batch_id,
            $movement->warehouse_id,
            $movement->location_to_id,
            $quantity,
        );

        event(new StockMovementCreated(
            movementId: $movement->id,
            productVariantId: $movement->product_variant_id,
            warehouseId: $movement->warehouse_id,
            movementType: 'adjustment',
            quantity: $quantity,
        ));
    }

    private function applyNegativeAdjustment(StockMovementModel $movement): void
    {
        $quantity = abs((float) $movement->quantity);

        $this->batchLocationService->withdraw(
            $movement->batch_id,
            $movement->warehouse_id,
            $quantity,
            $movement->location_from_id,
        );

        event(new StockMovementCreated(
            movementId: $movement->id,
            productVariantId: $movement->product_variant_id,
            warehouseId: $movement->warehouse_id,
            movementType: 'adjustment',
            quantity: $quantity,
        ));
    }
}
