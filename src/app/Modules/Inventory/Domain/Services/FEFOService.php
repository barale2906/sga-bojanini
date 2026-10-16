<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Services;

use App\Modules\Inventory\Domain\Exceptions\ExpiredStockException;
use App\Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Selección de lotes para movimientos que retiran stock de un almacén.
 *
 * Toda cantidad se mide DENTRO del almacén indicado (suma de las ubicaciones
 * del lote en ese almacén, en cualquier zona o estante); nunca se usa el
 * total global del lote (`batches.quantity_available`), que agrega todos
 * los almacenes.
 */
class FEFOService
{
    public function __construct(
        private readonly BatchLocationService $batchLocationService,
    ) {}

    /**
     * Selecciona lotes de una variante específica para cubrir una cantidad,
     * aplicando FEFO (First Expired, First Out).
     *
     * @return array<int, array{batch_id: int, product_variant_id: int, lot_number: string, quantity: float, expiration_date: string}>
     *
     * @throws ExpiredStockException
     * @throws InsufficientStockException
     */
    public function selectBatchesForExit(int $productVariantId, int $warehouseId, float $quantity, bool $includeExpired = false): array
    {
        return $this->select(
            fn (Builder $query) => $query->where('product_variant_id', $productVariantId),
            $warehouseId,
            $quantity,
            $includeExpired,
        );
    }

    /**
     * Selecciona lotes de TODAS las variantes de un genérico, aplicando FEFO
     * cruzado (primera fecha de vencimiento global, independiente de la variante).
     *
     * Usado en salidas donde el usuario pide por genérico y el sistema elige
     * la variante con el lote más próximo a vencer.
     *
     * @return array<int, array{batch_id: int, product_variant_id: int, lot_number: string, quantity: float, expiration_date: string}>
     *
     * @throws ExpiredStockException
     * @throws InsufficientStockException
     */
    public function selectBatchesForGenericExit(int $genericProductId, int $warehouseId, float $quantity, bool $includeExpired = false): array
    {
        $variantIds = DB::table('product_variants')
            ->where('generic_product_id', $genericProductId)
            ->where('is_active', true)
            ->pluck('id');

        if ($variantIds->isEmpty()) {
            throw new InsufficientStockException('El producto genérico no tiene variantes activas.');
        }

        return $this->select(
            fn (Builder $query) => $query->whereIn('product_variant_id', $variantIds),
            $warehouseId,
            $quantity,
            $includeExpired,
        );
    }

    /**
     * Valida un lote elegido explícitamente por el usuario y devuelve la
     * selección con el mismo formato que FEFO: el lote debe tener stock
     * suficiente en el almacén y, salvo `$includeExpired`, estar vigente.
     *
     * @return array<int, array{batch_id: int, product_variant_id: int, lot_number: string, quantity: float, expiration_date: string}>
     *
     * @throws ExpiredStockException
     * @throws InsufficientStockException
     */
    public function selectSpecificBatch(BatchModel $batch, int $warehouseId, float $quantity, bool $includeExpired = false): array
    {
        if (! $includeExpired) {
            $this->assertNotExpired($batch);
        }

        $available = $this->batchLocationService->quantityInWarehouse($batch->id, $warehouseId);

        if ($available < $quantity) {
            throw new InsufficientStockException(
                "El lote {$batch->lot_number} solo tiene {$available} unidades disponibles en este almacén, se solicitaron {$quantity}."
            );
        }

        return [$this->toSelection($batch, $quantity)];
    }

    /**
     * Impide retirar como stock vigente un lote vencido (por estado o por fecha).
     *
     * @throws ExpiredStockException
     */
    public function assertNotExpired(BatchModel $batch): void
    {
        $expiredByDate = $batch->expiration_date !== null
            && $batch->expiration_date->copy()->startOfDay()->lt(Carbon::today());

        if ($batch->status === 'expired' || $expiredByDate) {
            throw new ExpiredStockException(
                "El lote {$batch->lot_number} está vencido ({$batch->expiration_date?->format('Y-m-d')}). ".
                'Gestione el stock vencido mediante una devolución, un ajuste o una baja por vencimiento.'
            );
        }
    }

    /**
     * Suma la cantidad de lotes vencidos de una variante en un almacén.
     */
    public function getExpiredQuantity(int $productVariantId, int $warehouseId): float
    {
        return $this->expiredQuantity(
            fn (Builder $query) => $query->where('product_variant_id', $productVariantId),
            $warehouseId,
        );
    }

    /**
     * @param  callable(Builder): Builder  $scope  Restringe los lotes candidatos (variante o genérico).
     * @return array<int, array{batch_id: int, product_variant_id: int, lot_number: string, quantity: float, expiration_date: string}>
     */
    private function select(callable $scope, int $warehouseId, float $quantity, bool $includeExpired): array
    {
        $batches = $scope($this->batchesInWarehouse($warehouseId))
            ->when(
                $includeExpired,
                fn ($query) => $query->whereIn('status', ['active', 'expired']),
                fn ($query) => $query->valid(),
            )
            ->orderBy('expiration_date')
            ->orderBy('batches.id')
            ->get();

        $totalAvailable = round((float) $batches->sum('warehouse_quantity'), 3);

        if ($totalAvailable < $quantity) {
            if (! $includeExpired && $this->expiredQuantity($scope, $warehouseId) > 0) {
                throw new ExpiredStockException(
                    'El producto tiene stock registrado en este almacén, pero corresponde a lotes vencidos. '.
                    'Gestione el stock vencido mediante una devolución, un ajuste o una baja por vencimiento antes de continuar.'
                );
            }

            throw new InsufficientStockException(
                "Stock insuficiente. Se requieren {$quantity} unidades pero solo hay {$totalAvailable} disponibles."
            );
        }

        return $this->pickFromBatches($batches, $quantity);
    }

    /** @param  callable(Builder): Builder  $scope */
    private function expiredQuantity(callable $scope, int $warehouseId): float
    {
        return (float) $scope($this->batchesInWarehouse($warehouseId))
            ->where(function ($query) {
                $query->where('status', 'expired')
                    ->orWhere(function ($q) {
                        $q->where('status', 'active')
                            ->whereDate('expiration_date', '<', Carbon::today());
                    });
            })
            ->sum('warehouse_stock.warehouse_quantity');
    }

    /**
     * Lotes con stock en el almacén, exponiendo la cantidad en ese almacén
     * como atributo `warehouse_quantity`.
     */
    private function batchesInWarehouse(int $warehouseId): Builder
    {
        return BatchModel::query()
            ->joinSub(
                $this->batchLocationService->warehouseStockQuery($warehouseId),
                'warehouse_stock',
                'warehouse_stock.batch_id',
                '=',
                'batches.id',
            )
            ->select('batches.*', 'warehouse_stock.warehouse_quantity');
    }

    /**
     * @return array<int, array{batch_id: int, product_variant_id: int, lot_number: string, quantity: float, expiration_date: string}>
     */
    private function pickFromBatches(Collection $batches, float $quantity): array
    {
        $selected  = [];
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = round(min($remaining, (float) $batch->warehouse_quantity), 3);

            $selected[] = $this->toSelection($batch, $take);

            $remaining = round($remaining - $take, 3);
        }

        return $selected;
    }

    /**
     * @return array{batch_id: int, product_variant_id: int, lot_number: string, quantity: float, expiration_date: string}
     */
    private function toSelection(BatchModel $batch, float $quantity): array
    {
        return [
            'batch_id'           => $batch->id,
            'product_variant_id' => $batch->product_variant_id,
            'lot_number'         => $batch->lot_number,
            'quantity'           => $quantity,
            'expiration_date'    => $batch->expiration_date->format('Y-m-d'),
        ];
    }
}
