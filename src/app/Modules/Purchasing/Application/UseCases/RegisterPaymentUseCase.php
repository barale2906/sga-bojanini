<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Domain\Enums\PaymentStatus;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderPaymentModel;
use Illuminate\Support\Facades\DB;

class RegisterPaymentUseCase
{
    public function execute(int $orderId, array $data, int $userId): PurchaseOrderModel
    {
        return DB::transaction(function () use ($orderId, $data, $userId) {
            $order = PurchaseOrderModel::with('payments')->findOrFail($orderId);

            if ($order->order_type !== 'expense') {
                throw new \DomainException('El registro de pagos solo aplica a órdenes de gasto.');
            }

            PurchaseOrderPaymentModel::create([
                'purchase_order_id' => $order->id,
                'amount'            => (float) $data['amount'],
                'payment_date'      => $data['payment_date'],
                'payment_method'    => $data['payment_method'],
                'reference'         => $data['reference'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'registered_by'     => $userId,
            ]);

            $this->recalculatePaymentStatus($order);

            return $order->fresh(['expenseItems', 'supplier', 'payments.registeredBy']);
        });
    }

    private function recalculatePaymentStatus(PurchaseOrderModel $order): void
    {
        $order->refresh()->load('payments');

        $amountPaid = (float) $order->payments->sum('amount');
        $total      = (float) $order->total_amount;

        $status = match (true) {
            $amountPaid <= 0          => PaymentStatus::Unpaid->value,
            $amountPaid >= $total     => PaymentStatus::Paid->value,
            default                   => PaymentStatus::Partial->value,
        };

        $order->update([
            'amount_paid'    => $amountPaid,
            'payment_status' => $status,
        ]);
    }
}
