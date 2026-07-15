<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Domain\Services\DocumentNumberGenerator;
use App\Modules\Inventory\Domain\ValueObjects\MovementStatus;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Facades\DB;

/**
 * Registra una baja de inventario por una causa distinta al vencimiento
 * (daño, muestra/donación, pérdida o robo, etc.).
 *
 * A diferencia de `WriteOffExpiredUseCase` (que da de baja un lote completo
 * por vencimiento), esta baja descuenta una cantidad puntual de un lote
 * específico elegido explícitamente por el usuario (`batch_id`): la
 * metodología FEFO no aplica aquí, ya que la baja debe poder dirigirse al
 * lote concreto que presenta el daño, la pérdida, etc. Puede gestionar
 * tanto stock vigente (p. ej. un producto dañado) como stock vencido.
 */
class RegisterLossUseCase
{
    public function __construct(
        private readonly DocumentNumberGenerator $numberGenerator,
    ) {}

    public function execute(array $data): StockMovementModel
    {
        return $this->createPending($data);
    }

    public function createPending(array $data): StockMovementModel
    {
        return DB::transaction(function () use ($data) {
            $batch = BatchModel::findOrFail($data['batch_id']);

            $pivotQuantity = (int) (DB::table('batch_location')
                ->where('batch_id', $batch->id)
                ->where('location_id', $data['location_id'])
                ->value('quantity') ?? 0);

            if ($data['quantity'] > $pivotQuantity) {
                throw new InsufficientStockException(
                    "El lote {$batch->lot_number} solo tiene {$pivotQuantity} unidades disponibles en esta ubicación, se pidieron {$data['quantity']}."
                );
            }

            $document = MovementDocumentModel::create([
                'document_number' => $this->numberGenerator->next('loss'),
                'document_type'   => 'loss',
                'warehouse_id'    => $data['warehouse_id'],
                'reason'          => $data['reason'],
                'user_id'         => $data['user_id'],
                'status'          => MovementStatus::PENDING_SIGNATURE->value,
            ]);

            return StockMovementModel::create([
                'movement_document_id' => $document->id,
                'warehouse_id'         => $data['warehouse_id'],
                'product_variant_id'   => $data['product_variant_id'],
                'batch_id'             => $batch->id,
                'location_from_id'     => $data['location_id'],
                'movement_type'        => 'loss',
                'quantity'             => -(int) $data['quantity'],
                'reason'               => $data['reason'],
                'user_id'              => $data['user_id'],
                'status'               => MovementStatus::PENDING_SIGNATURE->value,
            ]);
        });
    }

    public function applyStock(StockMovementModel $movement): void
    {
        DB::transaction(function () use ($movement) {
            $quantity = abs($movement->quantity);
            $batch    = BatchModel::findOrFail($movement->batch_id);

            $pivotQuantity = (int) (DB::table('batch_location')
                ->where('batch_id', $batch->id)
                ->where('location_id', $movement->location_from_id)
                ->value('quantity') ?? 0);

            if ($quantity > $pivotQuantity) {
                throw new InsufficientStockException(
                    "El lote {$batch->lot_number} solo tiene {$pivotQuantity} unidades disponibles en esta ubicación, se pidieron {$quantity}."
                );
            }

            $batch->quantity_available = max(0, $batch->quantity_available - $quantity);

            if ($batch->quantity_available === 0) {
                $batch->status = 'depleted';
            }

            $batch->save();

            DB::table('batch_location')
                ->where('batch_id', $batch->id)
                ->where('location_id', $movement->location_from_id)
                ->decrement('quantity', $quantity);

            event(new StockMovementCreated(
                movementId: $movement->id,
                productVariantId: $movement->product_variant_id,
                warehouseId: $movement->warehouse_id,
                movementType: 'loss',
                quantity: $quantity,
            ));
        });
    }
}
