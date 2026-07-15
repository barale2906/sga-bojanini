<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Listeners;

use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Inventory\Domain\Events\StockBelowReorderPoint;
use App\Modules\Shared\Application\Services\NotificationRecipientService;
use App\Modules\Shared\Infrastructure\Notifications\StockBelowReorderNotification;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;

class NotifyLowStock
{
    public function __construct(
        private readonly NotificationRecipientService $notificationService,
    ) {}

    public function handle(StockBelowReorderPoint $event): void
    {
        $generic = GenericProductModel::find($event->genericProductId);
        $warehouse = WarehouseModel::find($event->warehouseId);

        if ($generic === null) {
            return;
        }

        $this->notificationService->notifyByType(
            'stock_below_reorder',
            new StockBelowReorderNotification(
                productName: $generic->name,
                productCode: $generic->barcode ?? '',
                currentStock: $event->currentStock,
                reorderPoint: $event->reorderPoint,
                warehouseName: $warehouse?->name ?? 'Almacén',
            ),
        );
    }
}
