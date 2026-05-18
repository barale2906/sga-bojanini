<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Domain\Services\ApprovalEngine;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use Illuminate\Support\Facades\DB;

class SubmitForApprovalUseCase
{
    public function __construct(
        private readonly ApprovalEngine $approvalEngine,
    ) {}

    public function execute(int $orderId): PurchaseOrderModel
    {
        return DB::transaction(function () use ($orderId) {
            $order = PurchaseOrderModel::with('items')->findOrFail($orderId);

            if ($order->status !== PurchaseOrderStatus::Draft->value) {
                throw new \DomainException('Solo se pueden enviar a aprobación órdenes en estado borrador.');
            }

            if ($order->items->isEmpty()) {
                throw new \DomainException('La orden debe tener al menos un ítem.');
            }

            $this->approvalEngine->initiateApproval(
                ApprovalEngine::ENTITY_PURCHASE_ORDER,
                $order->id,
                ['total_amount' => (float) $order->total_amount],
            );

            $order->update(['status' => PurchaseOrderStatus::PendingApproval->value]);

            return $order->fresh(['items.product', 'items.presentation', 'supplier', 'warehouse']);
        });
    }
}
