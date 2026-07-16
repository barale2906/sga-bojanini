<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;

class SendPurchaseOrderUseCase
{
    public function execute(int $orderId): PurchaseOrderModel
    {
        $order = PurchaseOrderModel::findOrFail($orderId);

        if ($order->status !== PurchaseOrderStatus::Approved->value) {
            throw new \DomainException("Solo se pueden enviar órdenes en estado 'approved'.");
        }

        $order->update([
            'status'  => PurchaseOrderStatus::Sent->value,
            'sent_at' => now(),
        ]);

        return $order->fresh(['items.variant.genericProduct', 'items.presentation', 'supplier', 'warehouse']);
    }
}
