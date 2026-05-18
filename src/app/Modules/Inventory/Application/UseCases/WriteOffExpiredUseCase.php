<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Facades\DB;

class WriteOffExpiredUseCase
{
    public function execute(array $data): StockMovementModel
    {
        return DB::transaction(function () use ($data) {
            $batch = BatchModel::findOrFail($data['batch_id']);

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

            $movement = StockMovementModel::create([
                'warehouse_id'     => $data['warehouse_id'],
                'product_id'       => $batch->product_id,
                'batch_id'         => $batch->id,
                'location_from_id' => $data['location_id'] ?? null,
                'movement_type'    => 'expiration_write_off',
                'quantity'         => -$quantity,
                'reason'           => $data['reason'] ?? 'Baja por vencimiento',
                'user_id'          => $data['user_id'],
            ]);

            event(new StockMovementCreated(
                movementId: $movement->id,
                productId: $batch->product_id,
                warehouseId: $data['warehouse_id'],
                movementType: 'expiration_write_off',
                quantity: $quantity,
            ));

            return $movement;
        });
    }
}
