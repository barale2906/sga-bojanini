<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Services;

use App\Modules\Inventory\Domain\Exceptions\ExpiredStockException;
use App\Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FEFOService
{
    /**
     * Selecciona lotes de una variante específica para cubrir una cantidad,
     * aplicando FEFO (First Expired, First Out).
     *
     * @return array<int, array{batch_id: int, product_variant_id: int, lot_number: string, quantity: int, expiration_date: string}>
     *
     * @throws ExpiredStockException
     * @throws InsufficientStockException
     */
    public function selectBatchesForExit(int $productVariantId, int $warehouseId, int $quantity, bool $includeExpired = false): array
    {
        $batches = $this->baseQuery($productVariantId, $warehouseId)
            ->when(
                $includeExpired,
                fn ($query) => $query->whereIn('status', ['active', 'expired']),
                fn ($query) => $query->valid(),
            )
            ->orderBy('expiration_date', 'asc')
            ->get();

        $totalAvailable = $batches->sum('quantity_available');

        if ($totalAvailable < $quantity) {
            if (! $includeExpired && $this->hasExpiredStock($productVariantId, $warehouseId)) {
                throw new ExpiredStockException(
                    "El producto tiene stock registrado en este almacén, pero corresponde a lotes vencidos. ".
                    'Gestione el stock vencido mediante una devolución, un ajuste o una baja por vencimiento antes de continuar.'
                );
            }

            throw new InsufficientStockException(
                "Stock insuficiente. Se requieren {$quantity} unidades pero solo hay {$totalAvailable} disponibles."
            );
        }

        return $this->pickFromBatches($batches, $quantity);
    }

    /**
     * Selecciona lotes de TODAS las variantes de un genérico, aplicando FEFO
     * cruzado (primera fecha de vencimiento global, independiente de la variante).
     *
     * Usado en salidas donde el usuario pide por genérico y el sistema elige
     * la variante con el lote más próximo a vencer.
     *
     * @return array<int, array{batch_id: int, product_variant_id: int, lot_number: string, quantity: int, expiration_date: string}>
     *
     * @throws ExpiredStockException
     * @throws InsufficientStockException
     */
    public function selectBatchesForGenericExit(int $genericProductId, int $warehouseId, int $quantity, bool $includeExpired = false): array
    {
        $variantIds = DB::table('product_variants')
            ->where('generic_product_id', $genericProductId)
            ->where('is_active', true)
            ->pluck('id');

        if ($variantIds->isEmpty()) {
            throw new InsufficientStockException('El producto genérico no tiene variantes activas.');
        }

        $batches = BatchModel::whereIn('product_variant_id', $variantIds)
            ->where('quantity_available', '>', 0)
            ->whereHas('locations', function ($query) use ($warehouseId) {
                $query->whereHas('zone', function ($q) use ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                });
            })
            ->when(
                $includeExpired,
                fn ($query) => $query->whereIn('status', ['active', 'expired']),
                fn ($query) => $query->valid(),
            )
            ->orderBy('expiration_date', 'asc')
            ->get();

        $totalAvailable = $batches->sum('quantity_available');

        if ($totalAvailable < $quantity) {
            if (! $includeExpired) {
                $expiredQty = (int) BatchModel::whereIn('product_variant_id', $variantIds)
                    ->where('quantity_available', '>', 0)
                    ->whereHas('locations', function ($query) use ($warehouseId) {
                        $query->whereHas('zone', fn ($q) => $q->where('warehouse_id', $warehouseId));
                    })
                    ->where(function ($q) {
                        $q->where('status', 'expired')
                            ->orWhere(fn ($sq) => $sq->where('status', 'active')
                                ->whereDate('expiration_date', '<', Carbon::today()));
                    })
                    ->sum('quantity_available');

                if ($expiredQty > 0) {
                    throw new ExpiredStockException(
                        "El producto tiene stock registrado en este almacén, pero corresponde a lotes vencidos. ".
                        'Gestione el stock vencido mediante una devolución, un ajuste o una baja por vencimiento antes de continuar.'
                    );
                }
            }

            throw new InsufficientStockException(
                "Stock insuficiente. Se requieren {$quantity} unidades pero solo hay {$totalAvailable} disponibles."
            );
        }

        return $this->pickFromBatches($batches, $quantity);
    }

    /**
     * Suma la cantidad disponible de lotes vencidos de una variante en un almacén.
     */
    public function getExpiredQuantity(int $productVariantId, int $warehouseId): int
    {
        return (int) $this->baseQuery($productVariantId, $warehouseId)
            ->where(function ($query) {
                $query->where('status', 'expired')
                    ->orWhere(function ($q) {
                        $q->where('status', 'active')
                            ->whereDate('expiration_date', '<', Carbon::today());
                    });
            })
            ->sum('quantity_available');
    }

    private function hasExpiredStock(int $productVariantId, int $warehouseId): bool
    {
        return $this->getExpiredQuantity($productVariantId, $warehouseId) > 0;
    }

    private function baseQuery(int $productVariantId, int $warehouseId): Builder
    {
        return BatchModel::where('product_variant_id', $productVariantId)
            ->where('quantity_available', '>', 0)
            ->whereHas('locations', function ($query) use ($warehouseId) {
                $query->whereHas('zone', function ($q) use ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                });
            });
    }

    /**
     * @return array<int, array{batch_id: int, product_variant_id: int, lot_number: string, quantity: int, expiration_date: string}>
     */
    private function pickFromBatches(\Illuminate\Database\Eloquent\Collection $batches, int $quantity): array
    {
        $selected  = [];
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, $batch->quantity_available);

            $selected[] = [
                'batch_id'           => $batch->id,
                'product_variant_id' => $batch->product_variant_id,
                'lot_number'         => $batch->lot_number,
                'quantity'           => $take,
                'expiration_date'    => $batch->expiration_date->format('Y-m-d'),
            ];

            $remaining -= $take;
        }

        return $selected;
    }
}
