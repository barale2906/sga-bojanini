<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;

class CancelPurchaseOrderUseCase
{
    public function execute(int $orderId): PurchaseOrderModel
    {
        $order = PurchaseOrderModel::findOrFail($orderId);

        if ($order->status === PurchaseOrderStatus::Received->value) {
            throw new \DomainException('No se puede cancelar una orden ya recibida completamente.');
        }

        if ($order->status === PurchaseOrderStatus::Cancelled->value) {
            throw new \DomainException('La orden ya está cancelada.');
        }

        $order->update(['status' => PurchaseOrderStatus::Cancelled->value]);

        return $order->fresh(['items.product', 'items.presentation', 'supplier', 'warehouse']);
    }
}
