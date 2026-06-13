<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Services;

use App\Modules\Inventory\Domain\Exceptions\ExpiredStockException;
use App\Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class FEFOService
{
    /**
     * Selecciona los lotes de un producto para cubrir una cantidad, aplicando
     * la política FEFO (First Expired, First Out): se toma primero el lote
     * con la fecha de vencimiento más próxima.
     *
     * Por defecto ($includeExpired = false) solo se consideran lotes vigentes
     * (status = 'active' y expiration_date >= hoy). Esta restricción aplica a
     * salidas, transferencias y salidas de kit, donde no se debe entregar
     * producto vencido.
     *
     * Cuando $includeExpired = true también se incluyen lotes vencidos
     * (status = 'expired' o expiration_date < hoy). Esto se usa en
     * devoluciones y ajustes negativos, que son los movimientos encargados
     * de gestionar el stock vencido.
     *
     * @return array<int, array{batch_id: int, lot_number: string, quantity: int, expiration_date: string}>
     *
     * @throws ExpiredStockException     cuando no hay stock vigente suficiente pero existe stock vencido
     * @throws InsufficientStockException cuando no hay stock suficiente (vigente ni vencido)
     */
    public function selectBatchesForExit(int $productId, int $warehouseId, int $quantity, bool $includeExpired = false): array
    {
        $batches = $this->baseQuery($productId, $warehouseId)
            ->when(
                $includeExpired,
                fn ($query) => $query->whereIn('status', ['active', 'expired']),
                fn ($query) => $query->valid(),
            )
            ->orderBy('expiration_date', 'asc')
            ->get();

        $totalAvailable = $batches->sum('quantity_available');

        if ($totalAvailable < $quantity) {
            if (! $includeExpired && $this->hasExpiredStock($productId, $warehouseId)) {
                throw new ExpiredStockException(
                    "El producto tiene stock registrado en este almacén, pero corresponde a lotes vencidos. ".
                    'Gestione el stock vencido mediante una devolución, un ajuste o una baja por vencimiento antes de continuar.'
                );
            }

            throw new InsufficientStockException(
                "Stock insuficiente. Se requieren {$quantity} unidades pero solo hay {$totalAvailable} disponibles."
            );
        }

        $selected = [];
        $remaining = $quantity;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $takeFromThisBatch = min($remaining, $batch->quantity_available);

            $selected[] = [
                'batch_id'        => $batch->id,
                'lot_number'      => $batch->lot_number,
                'quantity'        => $takeFromThisBatch,
                'expiration_date' => $batch->expiration_date->format('Y-m-d'),
            ];

            $remaining -= $takeFromThisBatch;
        }

        return $selected;
    }

    /**
     * Indica si el producto tiene lotes vencidos con cantidad disponible en
     * el almacén indicado (status = 'expired', o status = 'active' con
     * expiration_date anterior a hoy cuando aún no se ha ejecutado el job
     * de marcado de vencidos).
     */
    private function hasExpiredStock(int $productId, int $warehouseId): bool
    {
        return $this->getExpiredQuantity($productId, $warehouseId) > 0;
    }

    /**
     * Suma la cantidad disponible de lotes vencidos (status = 'expired', o
     * status = 'active' con expiration_date anterior a hoy) de un producto
     * en un almacén.
     *
     * Se usa para informar al frontend cuánto stock vencido puede gestionarse
     * mediante devoluciones, ajustes negativos o bajas, ya que ese stock no
     * forma parte de `available_quantity` (que solo refleja stock vigente).
     */
    public function getExpiredQuantity(int $productId, int $warehouseId): int
    {
        return (int) $this->baseQuery($productId, $warehouseId)
            ->where(function ($query) {
                $query->where('status', 'expired')
                    ->orWhere(function ($q) {
                        $q->where('status', 'active')
                            ->whereDate('expiration_date', '<', Carbon::today());
                    });
            })
            ->sum('quantity_available');
    }

    private function baseQuery(int $productId, int $warehouseId): Builder
    {
        return BatchModel::where('product_id', $productId)
            ->where('quantity_available', '>', 0)
            ->whereHas('locations', function ($query) use ($warehouseId) {
                $query->whereHas('zone', function ($q) use ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                });
            });
    }
}
