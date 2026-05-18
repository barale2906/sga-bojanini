<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Persistence;

use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Inventory\Domain\Repositories\StockSummaryRepositoryInterface;
use App\Modules\Inventory\Domain\ValueObjects\ProductStockSummary;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockSummaryModel;

class EloquentStockSummaryRepository implements StockSummaryRepositoryInterface
{
    public function findByProductCode(string $productCode): ?ProductStockSummary
    {
        $product = ProductModel::where('code', $productCode)->first();

        if ($product === null) {
            return null;
        }

        $total = (int) StockSummaryModel::where('product_id', $product->id)
            ->sum('available_quantity');

        return new ProductStockSummary($productCode, $total);
    }
}
