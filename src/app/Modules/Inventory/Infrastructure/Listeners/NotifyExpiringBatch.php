<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Listeners;

use App\Modules\Inventory\Domain\Events\BatchExpiringSoon;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Shared\Application\Services\NotificationRecipientService;
use App\Modules\Shared\Infrastructure\Notifications\BatchExpiringSoonNotification;

class NotifyExpiringBatch
{
    public function __construct(
        private readonly NotificationRecipientService $notificationService,
    ) {}

    public function handle(BatchExpiringSoon $event): void
    {
        $batch = BatchModel::with(['product'])->find($event->batchId);

        if ($batch === null) {
            return;
        }

        $warehouseName = 'Almacén';

        $this->notificationService->notifyByType(
            'batch_expiring_soon',
            new BatchExpiringSoonNotification(
                productName: $batch->product->name ?? 'Producto',
                batchNumber: $event->lotNumber,
                expiryDate: $event->expirationDate,
                daysUntilExpiry: $event->daysUntilExpiry,
                warehouseName: $warehouseName,
                currentQuantity: $batch->quantity_available,
            ),
        );
    }
}
