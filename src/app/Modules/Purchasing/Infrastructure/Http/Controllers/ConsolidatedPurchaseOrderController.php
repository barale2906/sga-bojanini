<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use App\Modules\Purchasing\Application\UseCases\ConsolidatePurchaseOrdersUseCase;
use App\Modules\Purchasing\Application\UseCases\PreviewConsolidationUseCase;
use App\Modules\Purchasing\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Infrastructure\Http\Requests\ConsolidatePurchaseOrdersRequest;
use App\Modules\Purchasing\Infrastructure\Http\Resources\ConsolidatedPurchaseOrderResource;
use App\Modules\Purchasing\Infrastructure\Http\Resources\PurchaseOrderResource;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\ConsolidatedPurchaseOrderModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ConsolidatedPurchaseOrderController extends Controller
{
    use ApiResponse;

    public function consolidableSuppliers(): JsonResponse
    {
        $supplierIds = PurchaseOrderModel::where('status', PurchaseOrderStatus::Received->value)
            ->whereNull('consolidated_order_id')
            ->distinct()
            ->pluck('supplier_id');

        $suppliers = SupplierModel::whereIn('id', $supplierIds)
            ->orderBy('name')
            ->get(['id', 'name', 'tax_id']);

        return $this->success($suppliers, 'Proveedores con órdenes consolidables');
    }

    public function consolidable(Request $request): JsonResponse
    {
        $query = PurchaseOrderModel::with(['supplier', 'items.variant.genericProduct', 'items.presentation', 'warehouse'])
            ->where('status', PurchaseOrderStatus::Received->value)
            ->whereNull('consolidated_order_id');

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }

        $all      = $query->orderBy('created_at')->get();
        $dateFrom = $request->filled('date_from') ? $request->date('date_from') : null;
        $dateTo   = $request->filled('date_to')   ? $request->date('date_to')->endOfDay() : null;

        if ($dateFrom === null && $dateTo === null) {
            return $this->success([
                'in_range' => PurchaseOrderResource::collection($all),
                'pending'  => [],
            ], 'Órdenes consolidables');
        }

        $inRange = $all->filter(function (PurchaseOrderModel $order) use ($dateFrom, $dateTo) {
            $created = $order->created_at;
            return ($dateFrom === null || $created >= $dateFrom)
                && ($dateTo === null   || $created <= $dateTo);
        });

        $pending = $all->filter(function (PurchaseOrderModel $order) use ($dateFrom) {
            return $dateFrom !== null && $order->created_at < $dateFrom;
        });

        return $this->success([
            'in_range' => PurchaseOrderResource::collection($inRange->values()),
            'pending'  => PurchaseOrderResource::collection($pending->values()),
        ], 'Órdenes consolidables');
    }

    public function preview(ConsolidatePurchaseOrdersRequest $request, PreviewConsolidationUseCase $useCase): JsonResponse
    {
        try {
            $data = $useCase->execute($request->validated('purchase_order_ids'));
        } catch (\DomainException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($data, 'Vista previa de consolidación');
    }

    public function store(ConsolidatePurchaseOrdersRequest $request, ConsolidatePurchaseOrdersUseCase $useCase): JsonResponse
    {
        $consolidated = $useCase->execute(
            $request->validated('purchase_order_ids'),
            $request->user()->id,
            $request->validated('notes'),
        );

        return $this->created(new ConsolidatedPurchaseOrderResource($consolidated), 'Orden consolidada creada');
    }

    public function index(Request $request): JsonResponse
    {
        $query = ConsolidatedPurchaseOrderModel::with(['supplier', 'createdBy']);

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->string('date_to'));
        }

        $perPage = min(
            (int) $request->query('per_page', config('sga.pagination.default_per_page', 25)),
            config('sga.pagination.max_per_page', 100),
        );

        return $this->paginated(
            $query->orderByDesc('created_at')
                ->paginate($perPage)
                ->through(fn ($c) => new ConsolidatedPurchaseOrderResource($c)),
            'Listado de órdenes consolidadas',
        );
    }

    public function show(int $id): JsonResponse
    {
        $consolidated = ConsolidatedPurchaseOrderModel::with([
            'supplier',
            'createdBy',
            'items.variant.genericProduct',
            'items.presentation',
            'purchaseOrders.warehouse',
            'purchaseOrders.items.variant.genericProduct',
            'purchaseOrders.items.presentation',
        ])->findOrFail($id);

        return $this->success(new ConsolidatedPurchaseOrderResource($consolidated), 'Detalle de orden consolidada');
    }
}
