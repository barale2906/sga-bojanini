<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Application\UseCases\Concerns\ManagesPurchaseOrderItems;
use App\Modules\Purchasing\Application\UseCases\Concerns\ManagesExpenseOrderItems;
use App\Modules\Purchasing\Domain\Enums\PaymentStatus;
use App\Modules\Purchasing\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use Illuminate\Support\Facades\DB;

class CreateExpenseOrderUseCase
{
    use ManagesPurchaseOrderItems;
    use ManagesExpenseOrderItems;

    public function execute(array $data, int $userId): PurchaseOrderModel
    {
        return DB::transaction(function () use ($data, $userId) {
            $built = $this->buildExpenseItems($data['items']);

            $order = PurchaseOrderModel::create([
                'order_type'             => 'expense',
                'supplier_id'            => $data['supplier_id'],
                'warehouse_id'           => null,
                'code'                   => $this->generateExpenseOrderCode(),
                'status'                 => PurchaseOrderStatus::Draft->value,
                'payment_status'         => PaymentStatus::Unpaid->value,
                'subtotal'               => $built['subtotal'],
                'tax_amount'             => $built['tax_amount'],
                'total_amount'           => $built['total_amount'],
                'notes'                  => $data['notes'] ?? null,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'created_by'             => $userId,
            ]);

            $this->syncExpenseItems($order, $built['items']);

            return $order->fresh(['expenseItems', 'supplier', 'payments']);
        });
    }
}
