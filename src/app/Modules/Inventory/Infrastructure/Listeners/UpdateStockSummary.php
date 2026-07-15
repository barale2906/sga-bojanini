<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Listeners;

use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Services\StockCalculator;

class UpdateStockSummary
{
    public function __construct(
        private readonly StockCalculator $stockCalculator,
    ) {}

    public function handle(StockMovementCreated $event): void
    {
        $this->stockCalculator->recalculateSummary($event->productVariantId, $event->warehouseId);

        if ($event->warehouseToId !== null && $event->warehouseToId !== $event->warehouseId) {
            $this->stockCalculator->recalculateSummary($event->productVariantId, $event->warehouseToId);
        }
    }
}
