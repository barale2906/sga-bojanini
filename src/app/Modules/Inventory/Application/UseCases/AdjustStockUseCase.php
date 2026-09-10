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
     * Para ajustes negativos corre FEFO para detectar stock insuficiente o vencido.
     * Para ajustes positivos verifica que exista un lote activo.
     */
    public function createPending(array $data): StockMovementModel
    {
        return DB::transaction(function () use ($data) {
            $quantity = (float) $data['quantity'];

            if ($quantity == 0) {
                throw new \DomainException('La cantidad de ajuste no puede ser cero.');
            }

            if (! empty($data['batch_id'])) {
                $batch = BatchModel::findOrFail((int) $data['batch_id']);

                if ((int) $batch->product_variant_id !== (int) $data['product_variant_id']) {
                    throw new \DomainException(
                        "El lote '{$batch->lot_number}' no corresponde a la variante indicada."
                    );
                }

                if ($quantity < 0 && $batch->quantity_available < abs($quantity)) {
                    throw new \App\Modules\Inventory\Domain\Exceptions\InsufficientStockException(
                        "El lote {$batch->lot_number} solo tiene {$batch->quantity_available} unidades disponibles, se solicitaron " . abs($quantity) . '.'
                    );
                }

                $firstBatchId = $batch->id;
            } elseif ($quantity < 0) {
                // Los ajustes negativos también gestionan stock vencido (p. ej.
                // descartes por conteo físico), por lo que se incluyen lotes con
                // expiration_date pasada.
                $selectedBatches = $this->fefoService->selectBatchesForExit(
                    $data['product_variant_id'],
                    $data['warehouse_id'],
                    abs($quantity),
                    includeExpired: true,
                );

                $firstBatchId = $selectedBatches[0]['batch_id'];
            } else {
                $batch = BatchModel::where('product_variant_id', $data['product_variant_id'])
                    ->where('status', 'active')
                    ->orderBy('expiration_date')
                    ->first();

                if ($batch === null) {
                    throw new \DomainException('No hay lotes activos para ajustar.');
                }

                $firstBatchId = $batch->id;
            }

            $document = MovementDocumentModel::create([
                'document_number' => $this->numberGenerator->next('adjustment'),
                'document_type'   => 'adjustment',
                'warehouse_id'    => $data['warehouse_id'],
                'reason'          => $data['reason'],
                'user_id'         => $data['user_id'],
                'status'          => MovementStatus::PENDING_SIGNATURE->value,
            ]);

            return StockMovementModel::create([
                'movement_document_id' => $document->id,
                'warehouse_id'         => $data['warehouse_id'],
                'product_variant_id'   => $data['product_variant_id'],
                'batch_id'             => $firstBatchId,
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
        $quantity = $movement->quantity;
        $batch    = BatchModel::findOrFail($movement->batch_id);

        $batch->quantity_available += $quantity;
        $batch->quantity_received  += $quantity;
        $batch->save();

        $locationId = $movement->location_to_id;

        $pivot = DB::table('batch_location')
            ->where('batch_id', $batch->id)
            ->where('location_id', $locationId)
            ->first();

        if ($pivot) {
            DB::table('batch_location')
                ->where('batch_id', $batch->id)
                ->where('location_id', $locationId)
                ->update([
                    'quantity'   => $pivot->quantity + $quantity,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('batch_location')->insert([
                'batch_id'    => $batch->id,
                'location_id' => $locationId,
                'quantity'    => $quantity,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

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
        $quantity = abs($movement->quantity);
        $batch    = BatchModel::findOrFail($movement->batch_id);

        if ($batch->quantity_available < $quantity) {
            throw new \App\Modules\Inventory\Domain\Exceptions\InsufficientStockException(
                "El lote {$batch->lot_number} solo tiene {$batch->quantity_available} unidades disponibles al confirmar el ajuste."
            );
        }

        $batch->quantity_available -= $quantity;

        if ($batch->quantity_available <= 0) {
            $batch->quantity_available = 0;
            $batch->status = 'depleted';
        }

        $batch->save();

        $this->batchLocationService->decrement($batch->id, $quantity, $movement->location_from_id);

        event(new StockMovementCreated(
            movementId: $movement->id,
            productVariantId: $movement->product_variant_id,
            warehouseId: $movement->warehouse_id,
            movementType: 'adjustment',
            quantity: $quantity,
        ));
    }
}
