<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Domain\Services\ApprovalEngine;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use App\Modules\Shared\Application\Services\NotificationRecipientService;
use App\Modules\Shared\Infrastructure\Notifications\PurchaseOrderPendingApprovalNotification;
use Illuminate\Support\Facades\DB;

class SubmitForApprovalUseCase
{
    public function __construct(
        private readonly ApprovalEngine $approvalEngine,
        private readonly NotificationRecipientService $notificationService,
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

            $order = $order->fresh(['items.variant', 'items.presentation', 'supplier', 'warehouse']);

            $this->notificationService->notifyByType(
                'purchase_order_pending_approval',
                new PurchaseOrderPendingApprovalNotification(
                    orderCode: $order->code,
                    totalAmount: (float) $order->total_amount,
                ),
            );

            return $order;
        });
    }
}
