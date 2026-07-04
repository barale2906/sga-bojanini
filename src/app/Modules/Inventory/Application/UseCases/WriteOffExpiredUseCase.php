<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\ValueObjects\MovementStatus;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Facades\DB;

class WriteOffExpiredUseCase
{
    public function execute(array $data): StockMovementModel
    {
        return $this->createPending($data);
    }

    public function createPending(array $data): StockMovementModel
    {
        return DB::transaction(function () use ($data) {
            $batch = BatchModel::findOrFail($data['batch_id']);

            if ($batch->quantity_available <= 0) {
                throw new \DomainException('El lote no tiene stock disponible para dar de baja.');
            }

            return StockMovementModel::create([
                'warehouse_id'     => $data['warehouse_id'],
                'product_id'       => $batch->product_id,
                'batch_id'         => $batch->id,
                'location_from_id' => $data['location_id'] ?? null,
                'movement_type'    => 'expiration_write_off',
                'quantity'         => -$batch->quantity_available,
                'reason'           => $data['reason'] ?? 'Baja por vencimiento',
                'user_id'          => $data['user_id'],
                'status'           => MovementStatus::PENDING_SIGNATURE->value,
            ]);
        });
    }

    public function applyStock(StockMovementModel $movement): void
    {
        DB::transaction(function () use ($movement) {
            $batch = BatchModel::findOrFail($movement->batch_id);

            if ($batch->quantity_available <= 0) {
                throw new \DomainException('El lote no tiene stock disponible para dar de baja.');
            }

            $quantity = $batch->quantity_available;

            $batch->quantity_available = 0;
            $batch->status = 'expired';
            $batch->save();

            DB::table('batch_location')
                ->where('batch_id', $batch->id)
                ->update(['quantity' => 0]);

            // Actualizar la cantidad real al momento de aplicar (pudo cambiar)
            $movement->quantity = -$quantity;
            $movement->save();

            event(new StockMovementCreated(
                movementId: $movement->id,
                productId: $movement->product_id,
                warehouseId: $movement->warehouse_id,
                movementType: 'expiration_write_off',
                quantity: $quantity,
            ));
        });
    }
}
