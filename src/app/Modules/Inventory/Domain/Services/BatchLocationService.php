<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Services;

use App\Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Domain\Exceptions\LocationOutsideWarehouseException;
use App\Modules\Inventory\Domain\ValueObjects\BatchStatus;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de escritura de `batch_location` (cantidad de un lote por ubicación).
 *
 * Toda operación está acotada a un almacén: una ubicación pertenece a un
 * almacén a través de su zona, y un movimiento solo puede afectar
 * ubicaciones de su propio almacén, sin importar en qué zona o estante
 * esté el producto. Tras cada cambio el lote se sincroniza para que
 * `batches.quantity_available` (total de todos los almacenes) y
 * `batches.status` reflejen exactamente lo que hay en las ubicaciones.
 */
class BatchLocationService
{
    /** Precisión de las columnas de cantidad (decimal(12,3)). */
    private const PRECISION = 3;

    /** Almacén al que pertenece una ubicación (vía su zona), o null si no existe. */
    public function warehouseOf(int $locationId): ?int
    {
        $warehouseId = DB::table('locations')
            ->join('zones', 'locations.zone_id', '=', 'zones.id')
            ->where('locations.id', $locationId)
            ->value('zones.warehouse_id');

        return $warehouseId === null ? null : (int) $warehouseId;
    }

    /** @throws LocationOutsideWarehouseException */
    public function assertLocationInWarehouse(int $locationId, int $warehouseId): void
    {
        if ($this->warehouseOf($locationId) !== $warehouseId) {
            throw new LocationOutsideWarehouseException(
                "La ubicación {$locationId} no pertenece al almacén {$warehouseId}. ".
                'Los movimientos solo pueden afectar ubicaciones del almacén en el que se registran.'
            );
        }
    }

    /**
     * Subconsulta con el stock de cada lote dentro de un almacén
     * (`batch_id`, `warehouse_quantity`), solo para lotes con cantidad > 0.
     */
    public function warehouseStockQuery(int $warehouseId): Builder
    {
        return DB::table('batch_location')
            ->join('locations', 'batch_location.location_id', '=', 'locations.id')
            ->join('zones', 'locations.zone_id', '=', 'zones.id')
            ->where('zones.warehouse_id', $warehouseId)
            ->groupBy('batch_location.batch_id')
            ->havingRaw('SUM(batch_location.quantity) > 0')
            ->select('batch_location.batch_id', DB::raw('SUM(batch_location.quantity) as warehouse_quantity'));
    }

    public function quantityInWarehouse(int $batchId, int $warehouseId): float
    {
        return $this->round((float) $this->pivotsInWarehouse($batchId, $warehouseId)->sum('batch_location.quantity'));
    }

    public function quantityInLocation(int $batchId, int $locationId): float
    {
        return $this->round((float) (DB::table('batch_location')
            ->where('batch_id', $batchId)
            ->where('location_id', $locationId)
            ->value('quantity') ?? 0));
    }

    /**
     * Ubicaciones del almacén con stock del lote, de mayor a menor cantidad.
     *
     * @return Collection<int, object{location_id: int, quantity: float}>
     */
    public function locationsWithStock(int $batchId, int $warehouseId): Collection
    {
        return $this->pivotsInWarehouse($batchId, $warehouseId)
            ->where('batch_location.quantity', '>', 0)
            ->orderByDesc('batch_location.quantity')
            ->orderBy('batch_location.location_id')
            ->get(['batch_location.location_id', 'batch_location.quantity']);
    }

    /**
     * Ingresa `$quantity` unidades del lote en una ubicación del almacén y
     * sincroniza el lote.
     *
     * @throws LocationOutsideWarehouseException
     */
    public function deposit(int $batchId, int $warehouseId, int $locationId, float $quantity): BatchModel
    {
        $this->assertLocationInWarehouse($locationId, $warehouseId);

        $pivot = DB::table('batch_location')
            ->where('batch_id', $batchId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if ($pivot) {
            DB::table('batch_location')
                ->where('batch_id', $batchId)
                ->where('location_id', $locationId)
                ->update([
                    'quantity'   => $this->round((float) $pivot->quantity + $quantity),
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('batch_location')->insert([
                'batch_id'    => $batchId,
                'location_id' => $locationId,
                'quantity'    => $this->round($quantity),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        return $this->syncBatch($batchId);
    }

    /**
     * Retira `$quantity` unidades del lote dentro del almacén y sincroniza el lote.
     *
     * Descuenta primero de `$preferredLocationId` (si se indica) y el remanente
     * de las demás ubicaciones del MISMO almacén, de mayor a menor cantidad.
     * Nunca toca ubicaciones de otros almacenes ni deja cantidades negativas.
     *
     * @throws LocationOutsideWarehouseException si la ubicación preferida es de otro almacén
     * @throws InsufficientStockException si el almacén no tiene stock suficiente del lote
     */
    public function withdraw(int $batchId, int $warehouseId, float $quantity, ?int $preferredLocationId = null): BatchModel
    {
        if ($preferredLocationId !== null) {
            $this->assertLocationInWarehouse($preferredLocationId, $warehouseId);
        }

        $pivots = $this->pivotsInWarehouse($batchId, $warehouseId)
            ->where('batch_location.quantity', '>', 0)
            ->lockForUpdate()
            ->get(['batch_location.location_id', 'batch_location.quantity'])
            ->sortBy(fn ($pivot) => [
                (int) $pivot->location_id === $preferredLocationId ? 0 : 1,
                -(float) $pivot->quantity,
                (int) $pivot->location_id,
            ]);

        $available = $this->round((float) $pivots->sum('quantity'));

        if ($available < $this->round($quantity)) {
            $lotNumber = BatchModel::whereKey($batchId)->value('lot_number');

            throw new InsufficientStockException(
                "El lote {$lotNumber} solo tiene {$available} unidades en este almacén, se requieren {$quantity}."
            );
        }

        $remaining = $this->round($quantity);

        foreach ($pivots as $pivot) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, $this->round((float) $pivot->quantity));

            DB::table('batch_location')
                ->where('batch_id', $batchId)
                ->where('location_id', $pivot->location_id)
                ->update([
                    'quantity'   => $this->round((float) $pivot->quantity - $take),
                    'updated_at' => now(),
                ]);

            $remaining = $this->round($remaining - $take);
        }

        return $this->syncBatch($batchId);
    }

    /**
     * Retira todo el stock del lote en el almacén (todas sus ubicaciones) y
     * sincroniza el lote.
     *
     * @return float Cantidad retirada.
     */
    public function clearWarehouse(int $batchId, int $warehouseId): float
    {
        $quantity = $this->quantityInWarehouse($batchId, $warehouseId);

        if ($quantity > 0) {
            $this->withdraw($batchId, $warehouseId, $quantity);
        }

        return $quantity;
    }

    /**
     * Recalcula `quantity_available` (suma de todas las ubicaciones) y el
     * estado del lote según su stock y su fecha de vencimiento.
     */
    public function syncBatch(int $batchId): BatchModel
    {
        $batch = BatchModel::lockForUpdate()->findOrFail($batchId);
        $total = $this->round((float) DB::table('batch_location')->where('batch_id', $batchId)->sum('quantity'));

        $batch->quantity_available = $total;
        $batch->status = BatchStatus::resolve($total, $batch->expiration_date, BatchStatus::tryFrom((string) $batch->status))->value;
        $batch->save();

        return $batch;
    }

    private function pivotsInWarehouse(int $batchId, int $warehouseId): Builder
    {
        return DB::table('batch_location')
            ->join('locations', 'batch_location.location_id', '=', 'locations.id')
            ->join('zones', 'locations.zone_id', '=', 'zones.id')
            ->where('batch_location.batch_id', $batchId)
            ->where('zones.warehouse_id', $warehouseId);
    }

    private function round(float $quantity): float
    {
        return round($quantity, self::PRECISION);
    }
}
