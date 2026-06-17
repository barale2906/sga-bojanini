<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Services;

use Illuminate\Support\Facades\DB;

/**
 * Mantiene sincronizada la tabla `batch_location` (cantidad de un lote por
 * ubicación) con `batches.quantity_available` cuando un movimiento descuenta
 * cantidad de un lote.
 *
 * `StockCalculator::recalculateSummary()` calcula `stock_summaries` a partir
 * de `batch_location`, por lo que cualquier descuento de `quantity_available`
 * debe reflejarse aquí para que el resumen de stock no quede desincronizado.
 */
class BatchLocationService
{
    /**
     * Descuenta `$quantity` unidades de un lote en `batch_location`.
     *
     * Si se indica `$preferredLocationId`, se descuenta primero de esa
     * ubicación (hasta donde alcance su cantidad); el remanente se reparte
     * entre las demás ubicaciones del lote que tengan cantidad disponible,
     * ordenadas por `location_id`. Esto evita dejar pivotes en negativo
     * cuando el lote seleccionado por FEFO (a nivel de almacén) tiene su
     * stock repartido en varias ubicaciones.
     *
     * Si `$preferredLocationId` es `null`, el descuento se reparte
     * directamente entre todas las ubicaciones del lote.
     */
    public function decrement(int $batchId, int $quantity, ?int $preferredLocationId = null): void
    {
        $remaining = $quantity;

        if ($preferredLocationId !== null) {
            $remaining = $this->decrementFromLocation($batchId, $preferredLocationId, $remaining);
        }

        if ($remaining <= 0) {
            return;
        }

        $pivots = DB::table('batch_location')
            ->where('batch_id', $batchId)
            ->where('quantity', '>', 0)
            ->when($preferredLocationId !== null, fn ($query) => $query->where('location_id', '!=', $preferredLocationId))
            ->orderBy('location_id')
            ->get();

        foreach ($pivots as $pivot) {
            if ($remaining <= 0) {
                break;
            }

            $remaining = $this->decrementFromLocation($batchId, $pivot->location_id, $remaining);
        }
    }

    private function decrementFromLocation(int $batchId, int $locationId, int $remaining): int
    {
        $pivot = DB::table('batch_location')
            ->where('batch_id', $batchId)
            ->where('location_id', $locationId)
            ->first();

        if ($pivot === null || $pivot->quantity <= 0) {
            return $remaining;
        }

        $take = min($remaining, $pivot->quantity);

        DB::table('batch_location')
            ->where('batch_id', $batchId)
            ->where('location_id', $locationId)
            ->decrement('quantity', $take);

        return $remaining - $take;
    }
}
