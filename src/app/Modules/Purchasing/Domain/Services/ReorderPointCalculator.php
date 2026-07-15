<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Domain\Services;

use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockSummaryModel;
use Carbon\Carbon;

class ReorderPointCalculator
{
    public function generateSuggestions(): array
    {
        $consumptionDays = config('sga.reorder.consumption_days', 90);
        $suggestions = [];

        $generics = GenericProductModel::where('is_active', true)
            ->where('reorder_point', '>', 0)
            ->with('variants.suppliers')
            ->get();

        foreach ($generics as $generic) {
            $variantIds = $generic->variants->pluck('id');

            $totalExits = StockMovementModel::whereIn('product_variant_id', $variantIds)
                ->where('movement_type', 'exit')
                ->where('created_at', '>=', Carbon::now()->subDays($consumptionDays))
                ->sum('quantity');

            $dailyConsumption = abs((float) $totalExits) / $consumptionDays;

            $preferredSupplier = null;
            foreach ($generic->variants as $variant) {
                $preferredSupplier = $variant->suppliers->firstWhere('pivot.is_preferred', true);
                if ($preferredSupplier !== null) {
                    break;
                }
            }

            $leadTimeDays = $preferredSupplier
                ? (int) $preferredSupplier->pivot->lead_time_days
                : 7;

            $currentStock = (int) StockSummaryModel::whereIn('product_variant_id', $variantIds)
                ->sum('available_quantity');

            $safetyStock = (int) ($generic->min_stock ?? 0);
            $suggestedQuantity = ($dailyConsumption * $leadTimeDays) + $safetyStock - $currentStock;

            if ($suggestedQuantity > 0) {
                $suggestions[] = [
                    'generic_product_id' => $generic->id,
                    'product_name'       => $generic->name,
                    'product_barcode'    => $generic->barcode,
                    'current_stock'      => $currentStock,
                    'reorder_point'      => $generic->reorder_point,
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
