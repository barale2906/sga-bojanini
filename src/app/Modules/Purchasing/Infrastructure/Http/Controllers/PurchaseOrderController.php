<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Controllers;

use App\Modules\Purchasing\Application\UseCases\ApproveOrRejectUseCase;
use App\Modules\Purchasing\Application\UseCases\CancelPurchaseOrderUseCase;
use App\Modules\Purchasing\Application\UseCases\CreatePurchaseOrderUseCase;
use App\Modules\Purchasing\Application\UseCases\DeletePurchaseOrderUseCase;
use App\Modules\Purchasing\Application\UseCases\GenerateReorderSuggestionsUseCase;
use App\Modules\Purchasing\Application\UseCases\ReceivePurchaseOrderUseCase;
use App\Modules\Purchasing\Application\UseCases\SendPurchaseOrderUseCase;
use App\Modules\Purchasing\Application\UseCases\SubmitForApprovalUseCase;
use App\Modules\Purchasing\Application\UseCases\UpdatePurchaseOrderUseCase;
use App\Modules\Purchasing\Infrastructure\Http\Requests\ApprovalDecisionRequest;
use App\Modules\Purchasing\Infrastructure\Http\Requests\ReceivePurchaseOrderRequest;
use App\Modules\Purchasing\Infrastructure\Http\Requests\StorePurchaseOrderRequest;
use App\Modules\Purchasing\Infrastructure\Http\Requests\UpdatePurchaseOrderRequest;
use App\Modules\Purchasing\Infrastructure\Http\Resources\PurchaseOrderResource;
use App\Modules\Purchasing\Infrastructure\Http\Resources\ReorderSuggestionResource;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PurchaseOrderController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = PurchaseOrderModel::with(['supplier', 'warehouse', 'items.variant']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

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
            $query->orderByDesc('created_at')->paginate($perPage)->through(fn ($order) => new PurchaseOrderResource($order)),
            'Listado de órdenes de compra',
        );
    }

    public function store(StorePurchaseOrderRequest $request, CreatePurchaseOrderUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute($request->validated(), $request->user()->id);

        return $this->created(new PurchaseOrderResource($order), 'Orden de compra creada');
    }

    public function show(int $id): JsonResponse
    {
        $order = PurchaseOrderModel::with(['supplier', 'warehouse', 'items.variant', 'items.presentation'])
            ->findOrFail($id);

        return $this->success(new PurchaseOrderResource($order), 'Detalle de orden de compra');
    }

    public function update(int $id, UpdatePurchaseOrderRequest $request, UpdatePurchaseOrderUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute($id, $request->validated());

        return $this->success(new PurchaseOrderResource($order), 'Orden de compra actualizada');
    }

    public function destroy(int $id, DeletePurchaseOrderUseCase $useCase): JsonResponse
    {
        $useCase->execute($id);

        return $this->noContent('Orden de compra eliminada');
    }

    public function submit(int $id, SubmitForApprovalUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute($id);

        return $this->success(new PurchaseOrderResource($order), 'Orden enviada a aprobación');
    }

    public function approve(int $id, ApprovalDecisionRequest $request, ApproveOrRejectUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute(
            $id,
            $request->user()->id,
            'approved',
            $request->validated('comments'),
            $request->validated('record_id'),
        );

        return $this->success(new PurchaseOrderResource($order), 'Paso de aprobación registrado');
    }

    public function reject(int $id, ApprovalDecisionRequest $request, ApproveOrRejectUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute(
            $id,
            $request->user()->id,
            'rejected',
            $request->validated('comments'),
            $request->validated('record_id'),
        );

        return $this->success(new PurchaseOrderResource($order), 'Orden rechazada');
    }

    public function send(int $id, SendPurchaseOrderUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute($id);

        return $this->success(new PurchaseOrderResource($order), 'Orden enviada al proveedor');
    }

    public function receive(int $id, ReceivePurchaseOrderRequest $request, ReceivePurchaseOrderUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute($id, $request->validated('items'), $request->user()->id);

        return $this->success(new PurchaseOrderResource($order), 'Recepción registrada');
    }

    public function cancel(int $id, CancelPurchaseOrderUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute($id);

        return $this->success(new PurchaseOrderResource($order), 'Orden cancelada');
    }

    public function suggestions(GenerateReorderSuggestionsUseCase $useCase): JsonResponse
    {
        $suggestions = $useCase->execute();

        return $this->success(
            ReorderSuggestionResource::collection(collect($suggestions)),
            'Sugerencias de reorden',
        );
    }
}
