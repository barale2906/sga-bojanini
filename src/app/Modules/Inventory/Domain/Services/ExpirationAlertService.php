<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Services;

use App\Modules\Inventory\Domain\Events\BatchExpiringSoon;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use Carbon\Carbon;

class ExpirationAlertService
{
    /**
     * @return array{expiring: int, expired: int}
     */
    public function checkExpiringBatches(?int $alertDays = null): array
    {
        $alertDays = $alertDays ?? config('sga.fefo.alert_days', 30);
        $alertDate = Carbon::today()->addDays($alertDays);

        $expiringBatches = BatchModel::where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->whereDate('expiration_date', '<=', $alertDate)
            ->whereDate('expiration_date', '>=', Carbon::today())
            ->get();

        foreach ($expiringBatches as $batch) {
            event(new BatchExpiringSoon(
                batchId: $batch->id,
                productId: $batch->product_id,
                lotNumber: $batch->lot_number,
                expirationDate: $batch->expiration_date->format('Y-m-d'),
                daysUntilExpiry: (int) now()->diffInDays($batch->expiration_date, false),
            ));
        }

        return [
            'expiring' => $expiringBatches->count(),
            'expired'  => $this->markExpiredBatches(),
        ];
    }

    public function markExpiredBatches(): int
    {
        return BatchModel::where('status', 'active')
            ->whereDate('expiration_date', '<', Carbon::today())
            ->update(['status' => 'expired']);
    }
}
