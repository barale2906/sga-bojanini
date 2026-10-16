<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain\Services;

use App\Modules\Inventory\Domain\Events\BatchExpiringSoon;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ExpirationAlertService
{
    public function __construct(
        private readonly StockCalculator $stockCalculator,
    ) {}

    /**
     * @return array{expiring: int, expired: int}
     */
    public function checkExpiringBatches(?int $alertDays = null): array
    {
        // Primero se marcan los vencidos: así una falla al notificar no deja
        // lotes vencidos disponibles como stock vigente.
        $expired = $this->markExpiredBatches();

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
                productId: $batch->product_variant_id,
                lotNumber: $batch->lot_number,
                expirationDate: $batch->expiration_date->format('Y-m-d'),
                daysUntilExpiry: (int) now()->diffInDays($batch->expiration_date, false),
            ));
        }

        return [
            'expiring' => $expiringBatches->count(),
            'expired'  => $expired,
        ];
    }

    /**
     * Marca como `expired` los lotes activos con fecha de vencimiento pasada y
     * recalcula el resumen de stock de cada almacén donde tienen existencias,
     * para que el stock vencido deje de mostrarse como disponible.
     */
    public function markExpiredBatches(): int
    {
        $batchIds = BatchModel::where('status', 'active')
            ->whereDate('expiration_date', '<', Carbon::today())
            ->pluck('id');

        if ($batchIds->isEmpty()) {
            return 0;
        }

        BatchModel::whereIn('id', $batchIds)->update(['status' => 'expired']);

        DB::table('batch_location')
            ->join('batches', 'batch_location.batch_id', '=', 'batches.id')
            ->join('locations', 'batch_location.location_id', '=', 'locations.id')
            ->join('zones', 'locations.zone_id', '=', 'zones.id')
            ->whereIn('batch_location.batch_id', $batchIds)
            ->distinct()
            ->get(['batches.product_variant_id', 'zones.warehouse_id'])
            ->each(fn ($pair) => $this->stockCalculator->recalculateSummary(
                (int) $pair->product_variant_id,
                (int) $pair->warehouse_id,
            ));

        return $batchIds->count();
    }
}
