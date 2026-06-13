<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
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
    public function execute(array $data): StockMovementModel
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

            $batch->quantity_available = max(0, $batch->quantity_available - $data['quantity']);

            if ($batch->quantity_available === 0) {
                $batch->status = 'depleted';
            }

            $batch->save();

            DB::table('batch_location')
                ->where('batch_id', $batch->id)
                ->where('location_id', $data['location_id'])
                ->decrement('quantity', $data['quantity']);

            $movement = StockMovementModel::create([
                'warehouse_id'     => $data['warehouse_id'],
                'product_id'       => $data['product_id'],
                'batch_id'         => $batch->id,
                'location_from_id' => $data['location_id'],
                'movement_type'    => 'loss',
                'quantity'         => -$data['quantity'],
                'reason'           => $data['reason'],
                'user_id'          => $data['user_id'],
            ]);

            event(new StockMovementCreated(
                movementId: $movement->id,
                productId: $data['product_id'],
                warehouseId: $data['warehouse_id'],
                movementType: 'loss',
                quantity: $data['quantity'],
            ));

            return $movement;
        });
    }
}
