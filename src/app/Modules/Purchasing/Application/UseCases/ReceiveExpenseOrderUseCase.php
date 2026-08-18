<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use Illuminate\Support\Facades\DB;

class ReceiveExpenseOrderUseCase
{
    public function execute(int $orderId, array $items): PurchaseOrderModel
    {
        return DB::transaction(function () use ($orderId, $items) {
            $order = PurchaseOrderModel::with('expenseItems')->findOrFail($orderId);

            if ($order->order_type !== 'expense') {
                throw new \DomainException('Esta orden no es de tipo gasto.');
            }

            if (! in_array($order->status, [
                PurchaseOrderStatus::Sent->value,
                PurchaseOrderStatus::PartiallyReceived->value,
            ], true)) {
                throw new \DomainException("Solo se pueden recibir órdenes en estado 'sent' o 'partially_received'.");
            }

            foreach ($items as $received) {
                $item = $order->expenseItems->firstWhere('id', $received['item_id']);

                if ($item === null) {
                    throw new \DomainException("Ítem {$received['item_id']} no pertenece a esta orden.");
                }

                $qty = (float) $received['quantity_received'];

                if ($qty <= 0) {
                    throw new \DomainException("La cantidad recibida debe ser mayor a cero (ítem {$item->id}).");
                }

                $maxReceivable = (float) $item->quantity_requested - (float) $item->quantity_received;

                if ($qty > $maxReceivable) {
                    throw new \DomainException(
                        "No se pueden recibir {$qty} del ítem {$item->id}. Máximo pendiente: {$maxReceivable}."
                    );
                }

                $item->quantity_received = (float) $item->quantity_received + $qty;
                $item->save();
            }

            $order->refresh()->load('expenseItems');

            $allReceived = $order->expenseItems->every(
                fn ($i) => (float) $i->quantity_received >= (float) $i->quantity_requested
            );

            $order->status = $allReceived
                ? PurchaseOrderStatus::Received->value
                : PurchaseOrderStatus::PartiallyReceived->value;

            if ($allReceived) {
                $order->received_at = now();
            }

            $order->save();

            return $order->fresh(['expenseItems', 'supplier', 'payments']);
        });
    }
}
