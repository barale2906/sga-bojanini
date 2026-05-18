<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Catalog\Domain\Services\PresentationConverter;
use App\Modules\Inventory\Application\UseCases\RegisterEntryUseCase;
use App\Modules\Purchasing\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use Illuminate\Support\Facades\DB;

class ReceivePurchaseOrderUseCase
{
    public function __construct(
        private readonly RegisterEntryUseCase $registerEntry,
        private readonly PresentationConverter $presentationConverter,
    ) {}

    public function execute(int $orderId, array $items, int $userId): PurchaseOrderModel
    {
        return DB::transaction(function () use ($orderId, $items, $userId) {
            $order = PurchaseOrderModel::with('items')->findOrFail($orderId);

            if (! in_array($order->status, [
                PurchaseOrderStatus::Sent->value,
                PurchaseOrderStatus::PartiallyReceived->value,
            ], true)) {
                throw new \DomainException("Solo se pueden recibir órdenes en estado 'sent' o 'partially_received'.");
            }

            foreach ($items as $receivedItem) {
                $orderItem = $order->items->firstWhere('id', $receivedItem['item_id']);

                if ($orderItem === null) {
                    throw new \DomainException("Item {$receivedItem['item_id']} no pertenece a esta orden.");
                }

                $qtyPresentation = (int) $receivedItem['quantity_received'];
                $maxReceivable = $orderItem->quantity_requested - $orderItem->quantity_received;

                if ($qtyPresentation > $maxReceivable) {
                    throw new \DomainException(
                        "No se pueden recibir {$qtyPresentation} unidades del item {$orderItem->id}. Máximo pendiente: {$maxReceivable}."
                    );
                }

                if ($qtyPresentation < 1) {
                    throw new \DomainException('La cantidad recibida debe ser mayor a cero.');
                }

                $qtyBase = $this->presentationConverter->toBase(
                    $orderItem->product_presentation_id,
                    $qtyPresentation,
                );

                $this->registerEntry->execute([
                    'product_id'    => $orderItem->product_id,
                    'warehouse_id'  => $order->warehouse_id,
                    'location_id'   => $receivedItem['location_id'],
                    'lot_number'    => $receivedItem['lot_number'],
                    'expiration_date' => $receivedItem['expiration_date'],
                    'quantity_base' => $qtyBase,
                    'notes'         => "Recepción de OC {$order->code}",
                    'user_id'       => $userId,
                ]);

                $orderItem->quantity_received += $qtyPresentation;
                $orderItem->quantity_received_base += $qtyBase;
                $orderItem->save();
            }

            $order->refresh()->load('items');

            $allReceived = $order->items->every(
                fn ($item) => $item->quantity_received >= $item->quantity_requested
            );

            $order->status = $allReceived
                ? PurchaseOrderStatus::Received->value
                : PurchaseOrderStatus::PartiallyReceived->value;

            if ($allReceived) {
                $order->received_at = now();
            }

            $order->save();

            return $order->fresh(['items.product', 'items.presentation', 'supplier', 'warehouse']);
        });
    }
}
