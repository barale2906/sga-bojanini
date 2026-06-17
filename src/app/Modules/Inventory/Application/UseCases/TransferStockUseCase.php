<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Services\BatchLocationService;
use App\Modules\Inventory\Domain\Services\FEFOService;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Facades\DB;

class TransferStockUseCase
{
    public function __construct(
        private readonly FEFOService $fefoService,
        private readonly BatchLocationService $batchLocationService,
    ) {}

    public function execute(array $data): StockMovementModel
    {
        return DB::transaction(function () use ($data) {
            $warehouseFromId = $data['warehouse_from_id'];
            $warehouseToId   = $data['warehouse_to_id'];

            $selectedBatches = $this->fefoService->selectBatchesForExit(
                $data['product_id'],
                $warehouseFromId,
                $data['quantity'],
            );

            foreach ($selectedBatches as $selection) {
                $batch = BatchModel::findOrFail($selection['batch_id']);

                $this->batchLocationService->decrement($batch->id, $selection['quantity'], $data['location_from_id']);

                $pivot = DB::table('batch_location')
                    ->where('batch_id', $batch->id)
                    ->where('location_id', $data['location_to_id'])
                    ->first();

                if ($pivot) {
                    DB::table('batch_location')
                        ->where('batch_id', $batch->id)
                        ->where('location_id', $data['location_to_id'])
                        ->update([
                            'quantity'   => $pivot->quantity + $selection['quantity'],
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('batch_location')->insert([
                        'batch_id'    => $batch->id,
                        'location_id' => $data['location_to_id'],
                        'quantity'    => $selection['quantity'],
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }

            $movement = StockMovementModel::create([
                'warehouse_id'     => $warehouseFromId,
                'warehouse_to_id'  => $warehouseToId,
                'product_id'       => $data['product_id'],
                'batch_id'         => $selectedBatches[0]['batch_id'],
                'location_from_id' => $data['location_from_id'],
                'location_to_id'   => $data['location_to_id'],
                'movement_type'    => 'transfer',
                'quantity'         => $data['quantity'],
                'reason'           => $data['reason'] ?? null,
                'user_id'          => $data['user_id'],
            ]);

            event(new StockMovementCreated(
                movementId: $movement->id,
                productId: $data['product_id'],
                warehouseId: $warehouseFromId,
                movementType: 'transfer',
                quantity: $data['quantity'],
                warehouseToId: $warehouseToId,
            ));

            return $movement;
        });
    }
}
