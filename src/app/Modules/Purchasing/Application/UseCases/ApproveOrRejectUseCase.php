<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Domain\Services\ApprovalEngine;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use Illuminate\Support\Facades\DB;

class ApproveOrRejectUseCase
{
    public function __construct(
        private readonly ApprovalEngine $approvalEngine,
    ) {}

    public function execute(int $orderId, int $userId, string $decision, ?string $comments = null, ?int $recordId = null): PurchaseOrderModel
    {
        return DB::transaction(function () use ($orderId, $userId, $decision, $comments, $recordId) {
            $order = PurchaseOrderModel::findOrFail($orderId);

            if ($order->status !== PurchaseOrderStatus::PendingApproval->value) {
                throw new \DomainException('La orden no está pendiente de aprobación.');
            }

            if ($recordId === null) {
                $record = $this->approvalEngine->findPendingRecordForUser(
                    ApprovalEngine::ENTITY_PURCHASE_ORDER,
                    $order->id,
                    $userId,
                );

                if ($record === null) {
                    throw new \DomainException('No hay pasos de aprobación pendientes para su rol.');
                }

                $recordId = $record->id;
            }

            $this->approvalEngine->processDecision($recordId, $userId, $decision, $comments);

            if ($this->approvalEngine->isRejected(ApprovalEngine::ENTITY_PURCHASE_ORDER, $order->id)) {
                $order->update(['status' => PurchaseOrderStatus::Rejected->value]);
            } elseif ($this->approvalEngine->isFullyApproved(ApprovalEngine::ENTITY_PURCHASE_ORDER, $order->id)) {
                $order->update(['status' => PurchaseOrderStatus::Approved->value]);
            }

            return $order->fresh(['items.product', 'items.presentation', 'supplier', 'warehouse']);
        });
    }
}
