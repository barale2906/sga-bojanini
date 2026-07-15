<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence;

use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Inventory\Domain\Repositories\StockSummaryRepositoryInterface;
use App\Modules\Inventory\Domain\ValueObjects\ProductStockSummary;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockSummaryModel;

class EloquentStockSummaryRepository implements StockSummaryRepositoryInterface
{
    public function findByProductCode(string $productCode): ?ProductStockSummary
    {
        $generic = GenericProductModel::where('barcode', $productCode)->first();

        if ($generic === null) {
            return null;
        }

        $variantIds = $generic->variants()->pluck('id');

        $total = (int) StockSummaryModel::whereIn('product_variant_id', $variantIds)
            ->sum('available_quantity');

        return new ProductStockSummary($productCode, $total);
    }
}
