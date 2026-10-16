<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Services\BatchLocationService;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Domain\Services\DocumentNumberGenerator;
use App\Modules\Inventory\Domain\Services\FEFOService;
use App\Modules\Inventory\Domain\ValueObjects\MovementStatus;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Facades\DB;

class TransferStockUseCase
{
    public function __construct(
        private readonly FEFOService $fefoService,
        private readonly BatchLocationService $batchLocationService,
        private readonly DocumentNumberGenerator $numberGenerator,
    ) {}

    /**
     * Crea un documento de traslado con líneas pendientes de firma.
     * $data['items'] = [['product_variant_id'=>, 'location_from_id'=>, 'location_to_id'=>, 'quantity'=>], ...]
     */
    public function execute(array $data): MovementDocumentModel
    {
        return DB::transaction(function () use ($data) {
            $document = MovementDocumentModel::create([
                'document_number' => $this->numberGenerator->next('transfer'),
                'document_type'   => 'transfer',
                'warehouse_id'    => $data['warehouse_from_id'],
                'warehouse_to_id' => $data['warehouse_to_id'],
                'movement_date'   => $data['movement_date'] ?? now(),
                'reason'          => $data['reason'] ?? null,
                'user_id'         => $data['user_id'],
                'status'          => MovementStatus::PENDING_SIGNATURE->value,
            ]);

            foreach ($data['items'] as $item) {
                $selectedBatches = $this->resolveSelectionsForItem($item, $data['warehouse_from_id']);

                foreach ($selectedBatches as $selection) {
                    $locationFromId = $this->resolveSourceLocation(
                        $selection['batch_id'],
                        $data['warehouse_from_id'],
                        $item['location_from_id'] ?? null,
                    );

                    StockMovementModel::create([
                        'movement_document_id' => $document->id,
                        'warehouse_id'         => $data['warehouse_from_id'],
                        'warehouse_to_id'      => $data['warehouse_to_id'],
                        'product_variant_id'   => $item['product_variant_id'],
                        'batch_id'             => $selection['batch_id'],
                        'location_from_id'     => $locationFromId,
                        'location_to_id'       => $item['location_to_id'],
                        'movement_type'        => 'transfer',
                        'quantity'             => $selection['quantity'],
                        'reason'               => $data['reason'] ?? null,
                        'movement_date'        => $document->movement_date,
                        'user_id'              => $data['user_id'],
                        'status'               => MovementStatus::PENDING_SIGNATURE->value,
                    ]);
                }
            }

            return $document->load(['movements.variant.genericProduct', 'movements.batch', 'warehouse', 'warehouseTo', 'user']);
        });
    }

    /**
     * Si el ítem trae `batch_id` explícito lo valida (variante, vigencia y stock
     * en el almacén de origen) y lo usa directamente. Si no, delega a FEFO.
     *
     * @return array<int, array{batch_id:int, quantity:float}>
     */
    private function resolveSelectionsForItem(array $item, int $warehouseFromId): array
    {
        if (! empty($item['batch_id'])) {
            $batch = BatchModel::findOrFail((int) $item['batch_id']);

            if ((int) $batch->product_variant_id !== (int) $item['product_variant_id']) {
                throw new \DomainException(
                    "El lote '{$batch->lot_number}' no corresponde a la variante indicada."
                );
            }

            return $this->fefoService->selectSpecificBatch($batch, $warehouseFromId, (float) $item['quantity']);
        }

        return $this->fefoService->selectBatchesForExit(
            $item['product_variant_id'],
            $warehouseFromId,
            (float) $item['quantity'],
        );
    }

    /**
     * Devuelve la ubicación de origen del lote dentro del almacén de origen.
     * Si el usuario envió location_from_id la prioriza, pero sólo si realmente
     * tiene stock; de lo contrario se elige la ubicación con mayor cantidad.
     */
    private function resolveSourceLocation(int $batchId, int $warehouseId, ?int $preferredLocationId): ?int
    {
        if ($preferredLocationId !== null) {
            $this->batchLocationService->assertLocationInWarehouse($preferredLocationId, $warehouseId);
        }

        $pivots = $this->batchLocationService->locationsWithStock($batchId, $warehouseId);

        if ($pivots->isEmpty()) {
            return $preferredLocationId;
        }

        if ($preferredLocationId !== null && $pivots->firstWhere('location_id', $preferredLocationId)) {
            return $preferredLocationId;
        }

        return (int) $pivots->first()->location_id;
    }

    /** Aplica los cambios de ubicación del lote. Llamado desde ConfirmMovementUseCase. */
    public function applyStock(StockMovementModel $movement): void
    {
        DB::transaction(function () use ($movement) {
            $quantity = (float) $movement->quantity;

            // El lote pudo vencer mientras el documento esperaba la firma.
            $this->fefoService->assertNotExpired(BatchModel::findOrFail($movement->batch_id));

            $this->batchLocationService->withdraw(
                $movement->batch_id,
                $movement->warehouse_id,
                $quantity,
                $movement->location_from_id,
            );

            $this->batchLocationService->deposit(
                $movement->batch_id,
                $movement->warehouse_to_id ?? $movement->warehouse_id,
                $movement->location_to_id,
                $quantity,
            );

            event(new StockMovementCreated(
                movementId:       $movement->id,
                productVariantId: $movement->product_variant_id,
                warehouseId:      $movement->warehouse_id,
                movementType:     'transfer',
                quantity:         $quantity,
                warehouseToId:    $movement->warehouse_to_id,
            ));
        });
    }
}
