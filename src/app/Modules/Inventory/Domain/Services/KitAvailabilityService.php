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

    public function getAvailableKits(int $kitGenericId, int $warehouseId): int
    {
        $components = $this->kitExplosionService->explode($kitGenericId, 1);

        if ($components === []) {
            return 0;
        }

        $available = PHP_INT_MAX;

        foreach ($components as $component) {
            $stock = $this->stockCalculator->getGenericStock(
                $component['component_generic_id'],
                $warehouseId,
            );

            $kitsFromComponent = intdiv($stock, $component['quantity_base']);
            $available = min($available, $kitsFromComponent);
        }

        return $available === PHP_INT_MAX ? 0 : $available;
    }
}
