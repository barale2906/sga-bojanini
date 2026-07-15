<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Persistence;

use App\Modules\Warehouse\Domain\Repositories\CapacityRepositoryInterface;
use Illuminate\Support\Facades\DB;

class EloquentCapacityRepository implements CapacityRepositoryInterface
{
    /**
     * Volumen y peso usados en una ubicación concreta.
     *
     * @return array{used_volume_cm3: float, used_weight_kg: float}
     */
    public function getLocationUsage(int $locationId): array
    {
        $row = DB::table('batch_location as bl')
            ->join('batches as b', 'bl.batch_id', '=', 'b.id')
            ->join('product_variants as pv', 'b.product_variant_id', '=', 'pv.id')
            ->join('product_generics as p', 'pv.generic_product_id', '=', 'p.id')
            ->where('bl.location_id', $locationId)
            ->where('b.status', 'active')
            ->selectRaw('
                COALESCE(SUM(bl.quantity * COALESCE(p.volume_cm3, 0)), 0) AS used_volume_cm3,
                COALESCE(SUM(bl.quantity * COALESCE(p.weight_kg, 0)), 0)  AS used_weight_kg
            ')
            ->first();

        return [
            'used_volume_cm3' => (float) ($row->used_volume_cm3 ?? 0),
            'used_weight_kg'  => (float) ($row->used_weight_kg  ?? 0),
        ];
    }

    /**
     * Uso agregado de todas las ubicaciones de una zona.
     *
     * @return array{used_volume_cm3: float, used_weight_kg: float}
     */
    public function getZoneUsage(int $zoneId): array
    {
        $row = DB::table('batch_location as bl')
            ->join('batches as b', 'bl.batch_id', '=', 'b.id')
            ->join('product_variants as pv', 'b.product_variant_id', '=', 'pv.id')
            ->join('product_generics as p', 'pv.generic_product_id', '=', 'p.id')
            ->join('locations as l', 'bl.location_id', '=', 'l.id')
            ->where('l.zone_id', $zoneId)
            ->where('b.status', 'active')
            ->whereNull('l.deleted_at')
            ->selectRaw('
                COALESCE(SUM(bl.quantity * COALESCE(p.volume_cm3, 0)), 0) AS used_volume_cm3,
                COALESCE(SUM(bl.quantity * COALESCE(p.weight_kg, 0)), 0)  AS used_weight_kg
            ')
            ->first();

        return [
            'used_volume_cm3' => (float) ($row->used_volume_cm3 ?? 0),
            'used_weight_kg'  => (float) ($row->used_weight_kg  ?? 0),
        ];
    }

    /**
     * Uso agregado de todas las ubicaciones de un almacén.
     *
     * @return array{used_volume_cm3: float, used_weight_kg: float}
     */
    public function getWarehouseUsage(int $warehouseId): array
    {
        $row = DB::table('batch_location as bl')
            ->join('batches as b', 'bl.batch_id', '=', 'b.id')
            ->join('product_variants as pv', 'b.product_variant_id', '=', 'pv.id')
            ->join('product_generics as p', 'pv.generic_product_id', '=', 'p.id')
            ->join('locations as l', 'bl.location_id', '=', 'l.id')
            ->join('zones as z', 'l.zone_id', '=', 'z.id')
            ->where('z.warehouse_id', $warehouseId)
            ->where('b.status', 'active')
            ->whereNull('l.deleted_at')
            ->whereNull('z.deleted_at')
            ->selectRaw('
                COALESCE(SUM(bl.quantity * COALESCE(p.volume_cm3, 0)), 0) AS used_volume_cm3,
                COALESCE(SUM(bl.quantity * COALESCE(p.weight_kg, 0)), 0)  AS used_weight_kg
            ')
            ->first();

        return [
            'used_volume_cm3' => (float) ($row->used_volume_cm3 ?? 0),
            'used_weight_kg'  => (float) ($row->used_weight_kg  ?? 0),
        ];
    }
}
