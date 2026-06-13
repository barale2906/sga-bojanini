<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Controllers;

use App\Modules\Inventory\Domain\Services\FEFOService;
use App\Modules\Inventory\Infrastructure\Http\Requests\StockSummaryRequest;
use App\Modules\Inventory\Infrastructure\Http\Resources\StockResource;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockSummaryModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StockController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = StockSummaryModel::with(['product', 'warehouse']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        $perPage = min(
            (int) $request->query('per_page', config('sga.pagination.default_per_page', 25)),
            config('sga.pagination.max_per_page', 100),
        );

        return $this->paginated(
            $query->orderByDesc('updated_at')->paginate($perPage)->through(fn ($summary) => new StockResource($summary)),
            'Stock por producto y almacén',
        );
    }

    /**
     * Resumen de stock de un producto en un almacén.
     *
     * Incluye `expired_quantity`: la suma de unidades de lotes vencidos
     * (no contabilizadas en `available_quantity`, que solo refleja stock
     * vigente). El frontend debe sumar ambos campos para determinar si una
     * devolución, ajuste negativo o baja puede gestionarse, ya que esos
     * movimientos sí pueden operar sobre stock vencido.
     */
    public function summary(StockSummaryRequest $request, FEFOService $fefoService): JsonResponse
    {
        $productId = $request->integer('product_id');
        $warehouseId = $request->integer('warehouse_id');

        $summary = StockSummaryModel::with(['product', 'warehouse'])
            ->where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->first();

        $expiredQuantity = $fefoService->getExpiredQuantity($productId, $warehouseId);

        if ($summary === null) {
            return $this->success([
                'product_id'         => $productId,
                'warehouse_id'       => $warehouseId,
                'total_quantity'     => 0,
                'reserved_quantity'  => 0,
                'available_quantity' => 0,
                'expired_quantity'   => $expiredQuantity,
                'last_movement_at'   => null,
            ], 'Resumen de stock');
        }

        $data = (new StockResource($summary))->toArray($request);
        $data['expired_quantity'] = $expiredQuantity;

        return $this->success($data, 'Resumen de stock');
    }

    public function low(Request $request): JsonResponse
    {
        $query = StockSummaryModel::query()
            ->with(['product', 'warehouse'])
            ->join('products', 'stock_summaries.product_id', '=', 'products.id')
            ->where('products.reorder_point', '>', 0)
            ->whereColumn('stock_summaries.available_quantity', '<=', 'products.reorder_point')
            ->select('stock_summaries.*');

        if ($request->filled('warehouse_id')) {
            $query->where('stock_summaries.warehouse_id', $request->integer('warehouse_id'));
        }

        $summaries = $query->get();

        return $this->success(StockResource::collection($summaries), 'Productos con stock bajo');
    }
}
