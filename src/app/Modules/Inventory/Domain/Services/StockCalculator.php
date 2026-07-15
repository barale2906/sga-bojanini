<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Services;

use App\Modules\Inventory\Infrastructure\Persistence\Models\StockSummaryModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StockCalculator
{
    public function getCurrentStock(int $productVariantId, int $warehouseId): int
    {
        $summary = StockSummaryModel::where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        return $summary ? $summary->available_quantity : 0;
    }

    /**
     * Suma el stock disponible de todas las variantes de un genérico en un almacén.
     */
    public function getGenericStock(int $genericProductId, int $warehouseId): int
    {
        return (int) StockSummaryModel::where('warehouse_id', $warehouseId)
            ->whereHas('variant', fn ($q) => $q->where('generic_product_id', $genericProductId))
            ->sum('available_quantity');
    }

    /**
     * Desglosa el stock vigente de una variante por ubicación dentro de un almacén.
     */
    public function getStockByLocation(int $productVariantId, int $warehouseId): array
    {
        return DB::table('batch_location')
            ->join('batches', 'batch_location.batch_id', '=', 'batches.id')
            ->join('locations', 'batch_location.location_id', '=', 'locations.id')
            ->join('zones', 'locations.zone_id', '=', 'zones.id')
            ->where('batches.product_variant_id', $productVariantId)
            ->where('zones.warehouse_id', $warehouseId)
            ->where('batches.status', 'active')
            ->whereDate('batches.expiration_date', '>=', Carbon::today())
            ->select(
                'locations.id as location_id',
                'locations.name as location_name',
                'locations.code as location_code',
                DB::raw('SUM(batch_location.quantity) as quantity'),
            )
            ->groupBy('locations.id', 'locations.name', 'locations.code')
            ->get()
            ->toArray();
    }

    /**
     * Recalcula el resumen de stock de una variante en un almacén a partir de
     * los lotes vigentes (status = 'active' y expiration_date >= hoy).
     */
    public function recalculateSummary(int $productVariantId, int $warehouseId): void
    {
        $totalQuantity = DB::table('batch_location')
            ->join('batches', 'batch_location.batch_id', '=', 'batches.id')
            ->join('locations', 'batch_location.location_id', '=', 'locations.id')
            ->join('zones', 'locations.zone_id', '=', 'zones.id')
            ->where('batches.product_variant_id', $productVariantId)
            ->where('zones.warehouse_id', $warehouseId)
            ->where('batches.status', 'active')
            ->whereDate('batches.expiration_date', '>=', Carbon::today())
            ->sum('batch_location.quantity');

        StockSummaryModel::updateOrCreate(
            [
                'product_variant_id' => $productVariantId,
                'warehouse_id'       => $warehouseId,
            ],
            [
                'total_quantity'     => $totalQuantity,
                'available_quantity' => $totalQuantity,
                'last_movement_at'   => now(),
            ],
        );
    }
}
