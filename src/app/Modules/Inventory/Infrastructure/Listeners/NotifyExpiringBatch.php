<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Listeners;

use App\Modules\Inventory\Domain\Events\BatchExpiringSoon;
use Illuminate\Support\Facades\Log;

class NotifyExpiringBatch
{
    public function handle(BatchExpiringSoon $event): void
    {
        Log::warning('Lote próximo a vencer', [
            'batch_id'        => $event->batchId,
            'product_id'      => $event->productId,
            'lot_number'      => $event->lotNumber,
            'expiration_date' => $event->expirationDate,
            'days_remaining'  => $event->daysUntilExpiry,
        ]);
    }
}
