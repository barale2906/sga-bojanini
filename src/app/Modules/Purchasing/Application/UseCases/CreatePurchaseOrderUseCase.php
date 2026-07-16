<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Catalog\Domain\Services\PresentationConverter;
use App\Modules\Purchasing\Application\UseCases\Concerns\ManagesPurchaseOrderItems;
use App\Modules\Purchasing\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use Illuminate\Support\Facades\DB;

class CreatePurchaseOrderUseCase
{
    use ManagesPurchaseOrderItems;

    public function __construct(
        private readonly PresentationConverter $presentationConverter,
    ) {}

    public function execute(array $data, int $userId): PurchaseOrderModel
    {
        return DB::transaction(function () use ($data, $userId) {
            $built = $this->validateAndBuildItems($data['items'], $this->presentationConverter);

            $order = PurchaseOrderModel::create([
                'supplier_id'            => $data['supplier_id'],
                'warehouse_id'           => $data['warehouse_id'],
                'code'                   => $this->generatePurchaseOrderCode(),
                'status'                 => PurchaseOrderStatus::Draft->value,
                'subtotal'               => $built['subtotal'],
                'tax_amount'             => $built['tax_amount'],
                'total_amount'           => $built['total_amount'],
                'notes'                  => $data['notes'] ?? null,
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'created_by'             => $userId,
            ]);

            $this->syncOrderItems($order, $built['items']);

            return $order->fresh(['items.variant.genericProduct', 'items.presentation', 'supplier', 'warehouse']);
        });
    }
}
