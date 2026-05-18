<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Domain\Services;

use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Carbon\Carbon;

class ReorderPointCalculator
{
    public function generateSuggestions(): array
    {
        $consumptionDays = config('sga.reorder.consumption_days', 90);
        $suggestions = [];

        $products = ProductModel::where('is_active', true)
            ->where('reorder_point', '>', 0)
            ->with('suppliers')
            ->get();

        foreach ($products as $product) {
            $totalExits = StockMovementModel::where('product_id', $product->id)
                ->where('movement_type', 'exit')
                ->where('created_at', '>=', Carbon::now()->subDays($consumptionDays))
                ->sum('quantity');

            $dailyConsumption = abs((float) $totalExits) / $consumptionDays;

            $preferredSupplier = $product->suppliers
                ->where('pivot.is_preferred', true)
                ->first();

            $leadTimeDays = $preferredSupplier
                ? (int) $preferredSupplier->pivot->lead_time_days
                : 7;

            $currentStock = (int) $product->stockSummaries()->sum('available_quantity');

            $safetyStock = (int) ($product->min_stock ?? 0);
            $suggestedQuantity = ($dailyConsumption * $leadTimeDays) + $safetyStock - $currentStock;

            if ($suggestedQuantity > 0) {
                $suggestions[] = [
                    'product_id'         => $product->id,
                    'product_name'       => $product->name,
                    'product_code'       => $product->code,
                    'current_stock'      => $currentStock,
                    'reorder_point'      => $product->reorder_point,
                    'daily_consumption'  => round($dailyConsumption, 2),
                    'lead_time_days'     => $leadTimeDays,
                    'safety_stock'       => $safetyStock,
                    'suggested_quantity' => (int) ceil($suggestedQuantity),
                    'preferred_supplier' => $preferredSupplier ? [
                        'id'   => $preferredSupplier->id,
                        'name' => $preferredSupplier->name,
                    ] : null,
                ];
            }
        }

        return $suggestions;
    }
}
