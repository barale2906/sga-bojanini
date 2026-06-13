<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Services;

use App\Modules\Inventory\Infrastructure\Persistence\Models\StockSummaryModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StockCalculator
{
    public function getCurrentStock(int $productId, int $warehouseId): int
    {
        $summary = StockSummaryModel::where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        return $summary ? $summary->available_quantity : 0;
    }

    /**
     * Desglosa el stock vigente (no vencido) de un producto por ubicación
     * dentro de un almacén.
     */
    public function getStockByLocation(int $productId, int $warehouseId): array
    {
        return DB::table('batch_location')
            ->join('batches', 'batch_location.batch_id', '=', 'batches.id')
            ->join('locations', 'batch_location.location_id', '=', 'locations.id')
            ->join('zones', 'locations.zone_id', '=', 'zones.id')
            ->where('batches.product_id', $productId)
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
     * Recalcula el resumen de stock de un producto en un almacén a partir de
     * los lotes vigentes (status = 'active' y expiration_date >= hoy).
     *
     * Los lotes vencidos no se contabilizan como stock disponible, sin
     * depender de que el job programado sga:check-expirations haya marcado
     * el lote como 'expired'.
     */
    public function recalculateSummary(int $productId, int $warehouseId): void
    {
        $totalQuantity = DB::table('batch_location')
            ->join('batches', 'batch_location.batch_id', '=', 'batches.id')
            ->join('locations', 'batch_location.location_id', '=', 'locations.id')
            ->join('zones', 'locations.zone_id', '=', 'zones.id')
            ->where('batches.product_id', $productId)
            ->where('zones.warehouse_id', $warehouseId)
            ->where('batches.status', 'active')
            ->whereDate('batches.expiration_date', '>=', Carbon::today())
            ->sum('batch_location.quantity');

        StockSummaryModel::updateOrCreate(
            [
                'product_id'   => $productId,
                'warehouse_id' => $warehouseId,
            ],
            [
                'total_quantity'     => $totalQuantity,
                'available_quantity' => $totalQuantity,
                'last_movement_at'   => now(),
            ],
        );
    }
}
