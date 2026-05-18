<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Repositories;

use App\Modules\Inventory\Domain\ValueObjects\ProductStockSummary;

interface StockSummaryRepositoryInterface
{
    public function findByProductCode(string $productCode): ?ProductStockSummary;
}
