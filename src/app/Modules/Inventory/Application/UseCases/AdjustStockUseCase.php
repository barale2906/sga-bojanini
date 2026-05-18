<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Services\FEFOService;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Facades\DB;

class AdjustStockUseCase
{
    public function __construct(
        private readonly FEFOService $fefoService,
    ) {}

    public function execute(array $data): StockMovementModel
    {
        return DB::transaction(function () use ($data) {
            $quantity = (int) $data['quantity'];

            if ($quantity === 0) {
                throw new \DomainException('La cantidad de ajuste no puede ser cero.');
            }

            if ($quantity > 0) {
                return $this->applyPositiveAdjustment($data, $quantity);
            }

            return $this->applyNegativeAdjustment($data, abs($quantity));
        });
    }

    private function applyPositiveAdjustment(array $data, int $quantity): StockMovementModel
    {
        $batch = BatchModel::where('product_id', $data['product_id'])
            ->where('status', 'active')
            ->orderBy('expiration_date')
            ->first();

        if ($batch === null) {
            throw new \DomainException('No hay lotes activos para ajustar.');
        }

        $batch->quantity_available += $quantity;
        $batch->quantity_received += $quantity;
        $batch->save();

        $pivot = DB::table('batch_location')
            ->where('batch_id', $batch->id)
            ->where('location_id', $data['location_id'])
            ->first();

        if ($pivot) {
            DB::table('batch_location')
                ->where('batch_id', $batch->id)
                ->where('location_id', $data['location_id'])
                ->update([
                    'quantity'   => $pivot->quantity + $quantity,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('batch_location')->insert([
                'batch_id'    => $batch->id,
                'location_id' => $data['location_id'],
                'quantity'    => $quantity,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        $movement = StockMovementModel::create([
            'warehouse_id'   => $data['warehouse_id'],
            'product_id'     => $data['product_id'],
            'batch_id'       => $batch->id,
            'location_to_id' => $data['location_id'],
            'movement_type'  => 'adjustment',
            'quantity'       => $quantity,
            'reason'         => $data['reason'],
            'user_id'        => $data['user_id'],
        ]);

        event(new StockMovementCreated(
            movementId: $movement->id,
            productId: $data['product_id'],
            warehouseId: $data['warehouse_id'],
            movementType: 'adjustment',
            quantity: $quantity,
        ));

        return $movement;
    }

    private function applyNegativeAdjustment(array $data, int $quantity): StockMovementModel
    {
        $selectedBatches = $this->fefoService->selectBatchesForExit(
            $data['product_id'],
            $data['warehouse_id'],
            $quantity,
        );

        foreach ($selectedBatches as $selection) {
            $batch = BatchModel::findOrFail($selection['batch_id']);
            $batch->quantity_available -= $selection['quantity'];

            if ($batch->quantity_available <= 0) {
                $batch->quantity_available = 0;
                $batch->status = 'depleted';
            }

            $batch->save();

            DB::table('batch_location')
                ->where('batch_id', $batch->id)
                ->where('location_id', $data['location_id'])
                ->decrement('quantity', $selection['quantity']);
        }

        $movement = StockMovementModel::create([
            'warehouse_id'     => $data['warehouse_id'],
            'product_id'       => $data['product_id'],
            'batch_id'         => $selectedBatches[0]['batch_id'],
            'location_from_id' => $data['location_id'],
            'movement_type'    => 'adjustment',
            'quantity'         => -$quantity,
            'reason'           => $data['reason'],
            'user_id'          => $data['user_id'],
        ]);

        event(new StockMovementCreated(
            movementId: $movement->id,
            productId: $data['product_id'],
            warehouseId: $data['warehouse_id'],
            movementType: 'adjustment',
            quantity: $quantity,
        ));

        return $movement;
    }
}
