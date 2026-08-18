<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;

class RegisterInvoiceUseCase
{
    public function execute(int $orderId, array $data): PurchaseOrderModel
    {
        $order = PurchaseOrderModel::findOrFail($orderId);

        if ($order->order_type !== 'expense') {
            throw new \DomainException('El registro de factura solo aplica a órdenes de gasto.');
        }

        $order->update([
            'invoice_number' => $data['invoice_number'],
            'invoice_date'   => $data['invoice_date'],
        ]);

        return $order->fresh(['expenseItems', 'supplier', 'payments']);
    }
}
