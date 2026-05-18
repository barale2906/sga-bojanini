<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Services;

use App\Modules\Catalog\Domain\Services\KitExplosionService;

class KitAvailabilityService
{
    public function __construct(
        private readonly KitExplosionService $kitExplosionService,
        private readonly StockCalculator $stockCalculator,
    ) {}

    public function getAvailableKits(int $kitProductId, int $warehouseId): int
    {
        $components = $this->kitExplosionService->explode($kitProductId, 1);

        if ($components === []) {
            return 0;
        }

        $available = PHP_INT_MAX;

        foreach ($components as $component) {
            $stock = $this->stockCalculator->getCurrentStock(
                $component['component_product_id'],
                $warehouseId,
            );

            $kitsFromComponent = intdiv($stock, $component['quantity_base']);
            $available = min($available, $kitsFromComponent);
        }

        return $available === PHP_INT_MAX ? 0 : $available;
    }
}
