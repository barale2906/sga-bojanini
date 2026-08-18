<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Application\DTOs\SupplierData;
use App\Modules\Catalog\Application\UseCases\CreateSupplierUseCase;
use App\Modules\Catalog\Infrastructure\Http\Resources\SupplierResource;
use App\Modules\Purchasing\Application\UseCases\ApproveOrRejectUseCase;
use App\Modules\Purchasing\Application\UseCases\CancelPurchaseOrderUseCase;
use App\Modules\Purchasing\Application\UseCases\CreateExpenseOrderUseCase;
use App\Modules\Purchasing\Application\UseCases\DeletePurchaseOrderUseCase;
use App\Modules\Purchasing\Application\UseCases\ReceiveExpenseOrderUseCase;
use App\Modules\Purchasing\Application\UseCases\RegisterInvoiceUseCase;
use App\Modules\Purchasing\Application\UseCases\SendExpenseOrderUseCase;
use App\Modules\Purchasing\Application\UseCases\SendToAccountingUseCase;
use App\Modules\Purchasing\Application\UseCases\SubmitForApprovalUseCase;
use App\Modules\Purchasing\Application\UseCases\UpdateExpenseOrderUseCase;
use App\Modules\Purchasing\Infrastructure\Http\Requests\ApprovalDecisionRequest;
use App\Modules\Purchasing\Infrastructure\Http\Requests\ReceiveExpenseOrderRequest;
use App\Modules\Purchasing\Infrastructure\Http\Requests\RegisterInvoiceRequest;
use App\Modules\Purchasing\Infrastructure\Http\Requests\SendToAccountingRequest;
use App\Modules\Purchasing\Infrastructure\Http\Requests\StoreExpenseOrderRequest;
use App\Modules\Purchasing\Infrastructure\Http\Requests\StoreExpenseSupplierRequest;
use App\Modules\Purchasing\Infrastructure\Http\Requests\UpdateExpenseOrderRequest;
use App\Modules\Purchasing\Infrastructure\Http\Resources\ExpenseOrderResource;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ExpenseOrderController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = PurchaseOrderModel::with(['supplier', 'expenseItems', 'payments'])
            ->where('order_type', 'expense');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->string('payment_status'));
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
            $query->orderByDesc('created_at')->paginate($perPage)->through(fn ($o) => new ExpenseOrderResource($o)),
            'Listado de órdenes de compra de gastos',
        );
    }

    public function store(StoreExpenseOrderRequest $request, CreateExpenseOrderUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute($request->validated(), $request->user()->id);

        return $this->created(new ExpenseOrderResource($order), 'Orden de compra de gastos creada');
    }

    public function show(int $id): JsonResponse
    {
        $order = PurchaseOrderModel::where('order_type', 'expense')
            ->with(['supplier', 'expenseItems', 'payments.registeredBy'])
            ->findOrFail($id);

        return $this->success(new ExpenseOrderResource($order), 'Detalle de orden de gastos');
    }

    public function update(int $id, UpdateExpenseOrderRequest $request, UpdateExpenseOrderUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute($id, $request->validated());

        return $this->success(new ExpenseOrderResource($order), 'Orden de gastos actualizada');
    }

    public function destroy(int $id, DeletePurchaseOrderUseCase $useCase): JsonResponse
    {
        $useCase->execute($id);

        return $this->noContent('Orden de gastos eliminada');
    }

    public function submit(int $id, SubmitForApprovalUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute($id);

        return $this->success(new ExpenseOrderResource($order), 'Orden enviada a aprobación');
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

        return $this->success(new ExpenseOrderResource($order), 'Paso de aprobación registrado');
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

        return $this->success(new ExpenseOrderResource($order), 'Orden rechazada');
    }

    public function send(int $id, SendExpenseOrderUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute($id);

        return $this->success(new ExpenseOrderResource($order), 'Orden enviada al proveedor');
    }

    public function receive(int $id, ReceiveExpenseOrderRequest $request, ReceiveExpenseOrderUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute($id, $request->validated('items'));

        return $this->success(new ExpenseOrderResource($order), 'Recepción registrada');
    }

    public function cancel(int $id, CancelPurchaseOrderUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute($id);

        return $this->success(new ExpenseOrderResource($order), 'Orden cancelada');
    }

    public function invoice(int $id, RegisterInvoiceRequest $request, RegisterInvoiceUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute($id, $request->validated());

        return $this->success(new ExpenseOrderResource($order), 'Factura registrada');
    }

    public function sendAccounting(int $id, SendToAccountingRequest $request, SendToAccountingUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute(
            $id,
            $request->validated('recipients'),
            $request->file('attachments', []),
        );

        return $this->success(new ExpenseOrderResource($order), 'Enviado a contabilidad');
    }

    public function storeSupplier(StoreExpenseSupplierRequest $request, CreateSupplierUseCase $useCase): JsonResponse
    {
        $data = new SupplierData(
            name: $request->validated('name'),
            taxId: $request->validated('tax_id'),
            contactName: $request->validated('contact_name'),
            phone: $request->validated('phone'),
            email: $request->validated('email'),
            notes: $request->validated('notes'),
            supplierType: 'expense',
        );

        $supplier = $useCase->execute($data);

        return $this->created(new SupplierResource($supplier), 'Proveedor creado');
    }
}
