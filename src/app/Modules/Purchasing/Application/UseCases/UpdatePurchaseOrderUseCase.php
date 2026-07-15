<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Catalog\Domain\Services\PresentationConverter;
use App\Modules\Purchasing\Application\UseCases\Concerns\ManagesPurchaseOrderItems;
use App\Modules\Purchasing\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use Illuminate\Support\Facades\DB;

class UpdatePurchaseOrderUseCase
{
    use ManagesPurchaseOrderItems;

    public function __construct(
        private readonly PresentationConverter $presentationConverter,
    ) {}

    public function execute(int $orderId, array $data): PurchaseOrderModel
    {
        return DB::transaction(function () use ($orderId, $data) {
            $order = PurchaseOrderModel::findOrFail($orderId);

            if ($order->status !== PurchaseOrderStatus::Draft->value) {
                throw new \DomainException('Solo se pueden editar órdenes en estado borrador.');
            }

            $built = $this->validateAndBuildItems($data['items'], $this->presentationConverter);

            $order->update([
                'supplier_id'            => $data['supplier_id'],
                'warehouse_id'           => $data['warehouse_id'],
                'subtotal'               => $built['subtotal'],
                'tax_amount'             => $built['tax_amount'],
                'total_amount'           => $built['total_amount'],
                'notes'                  => $data['notes'] ?? null,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
            ]);

            $this->syncOrderItems($order, $built['items']);

            return $order->fresh(['items.variant', 'items.presentation', 'supplier', 'warehouse']);
        });
    }
}
