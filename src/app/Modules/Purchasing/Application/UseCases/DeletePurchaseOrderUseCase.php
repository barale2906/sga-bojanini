<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;

class DeletePurchaseOrderUseCase
{
    public function execute(int $orderId): void
    {
        $order = PurchaseOrderModel::findOrFail($orderId);

        if ($order->status !== PurchaseOrderStatus::Draft->value) {
            throw new \DomainException('Solo se pueden eliminar órdenes en estado borrador.');
        }

        $order->delete();
    }
}
