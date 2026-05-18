<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Inventory\Domain\Events\StockBelowReorderPoint;
use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Inventory\Domain\Services\FEFOService;
use App\Modules\Inventory\Domain\Services\StockCalculator;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Facades\DB;

class RegisterExitUseCase
{
    public function __construct(
        private readonly FEFOService $fefoService,
        private readonly StockCalculator $stockCalculator,
        private readonly RegisterKitExitUseCase $registerKitExitUseCase,
    ) {}

    /**
     * @return StockMovementModel|array{kit_transaction: \App\Modules\Inventory\Infrastructure\Persistence\Models\KitTransactionModel, movements: \Illuminate\Support\Collection}
     */
    public function execute(array $data): StockMovementModel|array
    {
        $product = ProductModel::find($data['product_id']);

        if ($product === null) {
            throw new ProductNotFoundException("Producto {$data['product_id']} no encontrado.");
        }

        if ($product->product_type === ProductType::Kit->value) {
            return $this->registerKitExitUseCase->execute($data);
        }

        return DB::transaction(function () use ($data, $product) {
            $selectedBatches = $this->fefoService->selectBatchesForExit(
                $data['product_id'],
                $data['warehouse_id'],
                $data['quantity'],
            );

            foreach ($selectedBatches as $selection) {
                $batch = BatchModel::findOrFail($selection['batch_id']);
                $batch->quantity_available -= $selection['quantity'];

                if ($batch->quantity_available <= 0) {
                    $batch->quantity_available = 0;
                    $batch->status = 'depleted';
                }

                $batch->save();

                if (isset($data['location_id'])) {
                    DB::table('batch_location')
                        ->where('batch_id', $batch->id)
                        ->where('location_id', $data['location_id'])
                        ->decrement('quantity', $selection['quantity']);
                }
            }

            $movement = StockMovementModel::create([
                'warehouse_id'     => $data['warehouse_id'],
                'product_id'       => $data['product_id'],
                'batch_id'         => $selectedBatches[0]['batch_id'],
                'location_from_id' => $data['location_id'] ?? null,
                'movement_type'    => 'exit',
                'quantity'         => -$data['quantity'],
                'reason'           => $data['reason'] ?? null,
                'user_id'          => $data['user_id'],
            ]);

            event(new StockMovementCreated(
                movementId: $movement->id,
                productId: $data['product_id'],
                warehouseId: $data['warehouse_id'],
                movementType: 'exit',
                quantity: $data['quantity'],
            ));

            $currentStock = $this->stockCalculator->getCurrentStock(
                $data['product_id'],
                $data['warehouse_id'],
            );

            if ($product->reorder_point > 0 && $currentStock <= $product->reorder_point) {
                event(new StockBelowReorderPoint(
                    productId: $data['product_id'],
                    warehouseId: $data['warehouse_id'],
                    currentStock: $currentStock,
                    reorderPoint: $product->reorder_point,
                ));
            }

            return $movement;
        });
    }
}
