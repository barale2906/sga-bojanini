<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Infrastructure\Mail\ExpenseOrderAccountingMail;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use Illuminate\Support\Facades\Mail;

class SendToAccountingUseCase
{
    public function execute(int $orderId, array $recipients, array $attachments = []): PurchaseOrderModel
    {
        $order = PurchaseOrderModel::with(['expenseItems', 'supplier', 'payments.registeredBy', 'createdBy'])
            ->findOrFail($orderId);

        if ($order->order_type !== 'expense') {
            throw new \DomainException('Esta acción solo aplica a órdenes de gasto.');
        }

        if (empty($order->invoice_number)) {
            throw new \DomainException('Debe registrar la factura antes de enviar a contabilidad.');
        }

        $mailable = $attachments
            ? ExpenseOrderAccountingMail::withFiles($order, $attachments)
            : new ExpenseOrderAccountingMail($order);

        Mail::to($recipients)->queue($mailable);

        $order->update(['accounting_sent_at' => now()]);

        return $order->fresh(['expenseItems', 'supplier', 'payments']);
    }
}
