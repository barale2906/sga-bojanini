<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Controllers;

use App\Modules\Inventory\Domain\Services\FEFOService;
use App\Modules\Inventory\Domain\Services\KitAvailabilityService;
use App\Modules\Inventory\Infrastructure\Http\Requests\KitAvailabilityRequest;
use App\Modules\Inventory\Infrastructure\Http\Requests\StockSummaryRequest;
use App\Modules\Inventory\Infrastructure\Http\Resources\StockResource;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockSummaryModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Shared\Infrastructure\Http\Traits\ChecksWarehouseAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StockController extends Controller
{
    use ApiResponse;
    use ChecksWarehouseAccess;

    public function index(Request $request): JsonResponse
    {
        $query = StockSummaryModel::with(['variant.genericProduct', 'warehouse'])
            ->where('available_quantity', '>', 0);

        if ($request->filled('warehouse_id')) {
            $this->assertWarehouseAccess($request->user(), $request->integer('warehouse_id'));
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        } else {
            $this->scopeQueryToAllowedWarehouses($query, $request->user());
        }

        if ($request->filled('generic_product_id')) {
            $query->join('product_variants', 'stock_summaries.product_variant_id', '=', 'product_variants.id')
                  ->where('product_variants.generic_product_id', $request->integer('generic_product_id'))
                  ->select('stock_summaries.*');
        } elseif ($request->filled('product_variant_id')) {
            $query->where('product_variant_id', $request->integer('product_variant_id'));
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
        $productId = $request->integer('product_variant_id');
        $warehouseId = $request->integer('warehouse_id');

        $this->assertWarehouseAccess($request->user(), $warehouseId);

        $summary = StockSummaryModel::with(['variant.genericProduct', 'warehouse'])
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $productId)
            ->first();

        $expiredQuantity = $fefoService->getExpiredQuantity($productId, $warehouseId);

        if ($summary === null) {
            return $this->success([
                'product_variant_id'         => $productId,
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

    /**
     * Returns the number of kit units that can be assembled from current
     * component stock in a given warehouse.
     *
     * The calculation is: min(floor(stock_component / qty_per_kit)) for every
     * active component of the kit. A result of 0 means at least one component
     * has insufficient stock to assemble even a single unit.
     *
     * Use this endpoint before submitting a kit exit request so the UI can
     * surface availability information without triggering the exit transaction.
     */
    public function kitAvailability(KitAvailabilityRequest $request, KitAvailabilityService $service): JsonResponse
    {
        $kitGenericId = $request->integer('kit_generic_id');
        $warehouseId  = $request->integer('warehouse_id');

        $this->assertWarehouseAccess($request->user(), $warehouseId);

        try {
            $available = $service->getAvailableKits($kitGenericId, $warehouseId);
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'generic_product_id' => $kitGenericId,
            'warehouse_id'       => $warehouseId,
            'available_kits'     => $available,
        ], 'Disponibilidad de kit');
    }

    public function low(Request $request): JsonResponse
    {
        $query = StockSummaryModel::query()
            ->with(['variant.genericProduct', 'warehouse'])
            ->join('product_variants', 'stock_summaries.product_variant_id', '=', 'product_variants.id')
            ->join('product_generics', 'product_variants.generic_product_id', '=', 'product_generics.id')
            ->where('product_generics.reorder_point', '>', 0)
            ->whereColumn('stock_summaries.available_quantity', '<=', 'product_generics.reorder_point')
            ->select('stock_summaries.*');

        if ($request->filled('warehouse_id')) {
            $this->assertWarehouseAccess($request->user(), $request->integer('warehouse_id'));
            $query->where('stock_summaries.warehouse_id', $request->integer('warehouse_id'));
        } else {
            $this->scopeQueryToAllowedWarehouses($query, $request->user(), 'stock_summaries.warehouse_id');
        }

        $summaries = $query->get();

        return $this->success(StockResource::collection($summaries), 'Productos con stock bajo');
    }
}
