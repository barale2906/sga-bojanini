<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Domain\Enums\PaymentStatus;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderPaymentModel;
use Illuminate\Support\Facades\DB;

class DeletePaymentUseCase
{
    public function execute(int $orderId, int $paymentId): void
    {
        DB::transaction(function () use ($orderId, $paymentId) {
            $payment = PurchaseOrderPaymentModel::where('purchase_order_id', $orderId)
                ->findOrFail($paymentId);

            $payment->delete();

            $order = PurchaseOrderModel::with('payments')->findOrFail($orderId);

            $amountPaid = (float) $order->payments->sum('amount');
            $total      = (float) $order->total_amount;

            $status = match (true) {
                $amountPaid <= 0      => PaymentStatus::Unpaid->value,
                $amountPaid >= $total => PaymentStatus::Paid->value,
                default               => PaymentStatus::Partial->value,
            };

            $order->update([
                'amount_paid'    => $amountPaid,
                'payment_status' => $status,
            ]);
        });
    }
}
