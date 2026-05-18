<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Services;

use App\Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;

class FEFOService
{
    /**
     * @return array<int, array{batch_id: int, lot_number: string, quantity: int, expiration_date: string}>
     *
     * @throws InsufficientStockException
     */
    public function selectBatchesForExit(int $productId, int $warehouseId, int $quantity): array
    {
        $batches = BatchModel::where('product_id', $productId)
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->whereHas('locations', function ($query) use ($warehouseId) {
                $query->whereHas('zone', function ($q) use ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                });
            })
            ->orderBy('expiration_date', 'asc')
            ->get();

        $totalAvailable = $batches->sum('quantity_available');

        if ($totalAvailable < $quantity) {
            throw new InsufficientStockException(
                "Stock insuficiente. Se requieren {$quantity} unidades pero solo hay {$totalAvailable} disponibles."
            );
        }

        $selected = [];
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $takeFromThisBatch = min($remaining, $batch->quantity_available);

            $selected[] = [
                'batch_id'        => $batch->id,
                'lot_number'      => $batch->lot_number,
                'quantity'        => $takeFromThisBatch,
                'expiration_date' => $batch->expiration_date->format('Y-m-d'),
            ];

            $remaining -= $takeFromThisBatch;
        }

        return $selected;
    }
}
