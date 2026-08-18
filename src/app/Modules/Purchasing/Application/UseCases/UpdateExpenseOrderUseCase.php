<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Application\UseCases\Concerns\ManagesExpenseOrderItems;
use App\Modules\Purchasing\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use Illuminate\Support\Facades\DB;

class UpdateExpenseOrderUseCase
{
    use ManagesExpenseOrderItems;

    public function execute(int $orderId, array $data): PurchaseOrderModel
    {
        return DB::transaction(function () use ($orderId, $data) {
            $order = PurchaseOrderModel::findOrFail($orderId);

            if ($order->order_type !== 'expense') {
                throw new \DomainException('Esta orden no es de tipo gasto.');
            }

            if ($order->status !== PurchaseOrderStatus::Draft->value) {
                throw new \DomainException('Solo se pueden editar órdenes de gasto en estado borrador.');
            }

            $built = $this->buildExpenseItems($data['items']);

            $order->update([
                'supplier_id'            => $data['supplier_id'],
                'subtotal'               => $built['subtotal'],
                'tax_amount'             => $built['tax_amount'],
                'total_amount'           => $built['total_amount'],
                'notes'                  => $data['notes'] ?? $order->notes,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? $order->expected_delivery_date,
            ]);

            $this->syncExpenseItems($order, $built['items']);

            return $order->fresh(['expenseItems', 'supplier', 'payments']);
        });
    }
}
