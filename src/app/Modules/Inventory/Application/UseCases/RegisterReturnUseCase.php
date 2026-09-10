<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Domain\Services\BatchLocationService;
use App\Modules\Inventory\Domain\Services\DocumentNumberGenerator;
use App\Modules\Inventory\Domain\Services\FEFOService;
use App\Modules\Inventory\Domain\ValueObjects\MovementStatus;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Facades\DB;

class RegisterReturnUseCase
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
     * Valida disponibilidad (incluye lotes vencidos) y crea documento + registro pendiente.
     * Si se envía `batch_id` explícito se usa ese lote; de lo contrario aplica FEFO.
     */
    public function createPending(array $data): StockMovementModel
    {
        return DB::transaction(function () use ($data) {
            $quantity = (float) $data['quantity'];
            $batchId  = $this->resolveBatchId($data, $quantity);

            $document = MovementDocumentModel::create([
                'document_number' => $this->numberGenerator->next('return'),
                'document_type'   => 'return',
                'warehouse_id'    => $data['warehouse_id'],
                'reason'          => $data['reason'] ?? 'Devolución a proveedor',
                'user_id'         => $data['user_id'],
                'status'          => MovementStatus::PENDING_SIGNATURE->value,
            ]);

            return StockMovementModel::create([
                'movement_document_id' => $document->id,
                'warehouse_id'         => $data['warehouse_id'],
                'product_variant_id'   => $data['product_variant_id'],
                'batch_id'             => $batchId,
                'location_from_id'     => $data['location_id'] ?? null,
                'movement_type'        => 'return',
                'quantity'             => -$quantity,
                'reason'               => $data['reason'] ?? 'Devolución a proveedor',
                'user_id'              => $data['user_id'],
                'status'               => MovementStatus::PENDING_SIGNATURE->value,
            ]);
        });
    }

    private function resolveBatchId(array $data, float $quantity): int
    {
        if (! empty($data['batch_id'])) {
            $batch = BatchModel::findOrFail((int) $data['batch_id']);

            if ((int) $batch->product_variant_id !== (int) $data['product_variant_id']) {
                throw new \DomainException(
                    "El lote '{$batch->lot_number}' no corresponde a la variante indicada."
                );
            }

            if ($batch->quantity_available < $quantity) {
                throw new InsufficientStockException(
                    "El lote {$batch->lot_number} solo tiene {$batch->quantity_available} unidades disponibles, se solicitaron {$quantity}."
                );
            }

            return $batch->id;
        }

        $selectedBatches = $this->fefoService->selectBatchesForExit(
            $data['product_variant_id'],
            $data['warehouse_id'],
            $quantity,
            includeExpired: true,
        );

        return $selectedBatches[0]['batch_id'];
    }

    public function applyStock(StockMovementModel $movement): void
    {
        DB::transaction(function () use ($movement) {
            $quantity = abs($movement->quantity);
            $batch    = BatchModel::findOrFail($movement->batch_id);

            if ($batch->quantity_available < $quantity) {
                throw new InsufficientStockException(
                    "El lote {$batch->lot_number} solo tiene {$batch->quantity_available} unidades disponibles al confirmar la devolución."
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
                movementType: 'return',
                quantity: $quantity,
            ));
        });
    }
}
