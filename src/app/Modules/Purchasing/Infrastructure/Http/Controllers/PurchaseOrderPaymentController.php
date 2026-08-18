<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Controllers;

use App\Modules\Purchasing\Application\UseCases\DeletePaymentUseCase;
use App\Modules\Purchasing\Application\UseCases\RegisterPaymentUseCase;
use App\Modules\Purchasing\Infrastructure\Http\Requests\StorePaymentRequest;
use App\Modules\Purchasing\Infrastructure\Http\Resources\ExpenseOrderResource;
use App\Modules\Purchasing\Infrastructure\Http\Resources\PurchaseOrderPaymentResource;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class PurchaseOrderPaymentController extends Controller
{
    use ApiResponse;

    public function index(int $orderId): JsonResponse
    {
        $order = PurchaseOrderModel::where('order_type', 'expense')->findOrFail($orderId);

        $payments = $order->payments()->with('registeredBy')->orderByDesc('payment_date')->get();

        return $this->success(
            PurchaseOrderPaymentResource::collection($payments),
            'Pagos de la orden',
        );
    }

    public function store(int $orderId, StorePaymentRequest $request, RegisterPaymentUseCase $useCase): JsonResponse
    {
        $order = $useCase->execute($orderId, $request->validated(), $request->user()->id);

        return $this->created(new ExpenseOrderResource($order), 'Pago registrado');
    }

    public function destroy(int $orderId, int $paymentId, DeletePaymentUseCase $useCase): JsonResponse
    {
        $useCase->execute($orderId, $paymentId);

        return $this->noContent('Pago eliminado');
    }
}
