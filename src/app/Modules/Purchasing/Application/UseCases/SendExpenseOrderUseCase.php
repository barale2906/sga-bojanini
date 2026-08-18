<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Infrastructure\Mail\ExpenseOrderSupplierMail;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use Illuminate\Support\Facades\Mail;

class SendExpenseOrderUseCase
{
    public function execute(int $orderId): PurchaseOrderModel
    {
        $order = PurchaseOrderModel::with(['expenseItems', 'supplier', 'payments'])->findOrFail($orderId);

        if ($order->order_type !== 'expense') {
            throw new \DomainException('Esta orden no es de tipo gasto.');
        }

        if ($order->status !== PurchaseOrderStatus::Approved->value) {
            throw new \DomainException("Solo se pueden enviar órdenes en estado 'approved'.");
        }

        if (empty($order->supplier->email)) {
            throw new \DomainException('El proveedor no tiene correo electrónico registrado.');
        }

        $order->update([
            'status'  => PurchaseOrderStatus::Sent->value,
            'sent_at' => now(),
        ]);

        Mail::to($order->supplier->email)->queue(new ExpenseOrderSupplierMail($order->fresh()));

        return $order->fresh(['expenseItems', 'supplier', 'payments']);
    }
}
