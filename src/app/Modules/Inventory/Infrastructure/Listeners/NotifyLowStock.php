<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Listeners;

use App\Modules\Inventory\Domain\Events\StockBelowReorderPoint;
use Illuminate\Support\Facades\Log;

class NotifyLowStock
{
    public function handle(StockBelowReorderPoint $event): void
    {
        Log::warning('Stock por debajo del punto de reorden', [
            'product_id'    => $event->productId,
            'warehouse_id'  => $event->warehouseId,
            'current_stock' => $event->currentStock,
            'reorder_point' => $event->reorderPoint,
        ]);
    }
}
