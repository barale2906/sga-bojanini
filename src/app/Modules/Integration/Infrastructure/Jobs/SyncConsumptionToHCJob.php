<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\Jobs;

use App\Modules\Integration\Domain\Ports\ClinicalRecordsServiceInterface;
use App\Modules\Integration\Infrastructure\Persistence\Models\ConsumptionRecordModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncConsumptionToHCJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 20, 40];

    public function __construct(
        private readonly string $patientId,
        private readonly string $appointmentId,
        private readonly array $consumptionRecordIds,
    ) {}

    public function handle(ClinicalRecordsServiceInterface $clinicalService): void
    {
        $records = ConsumptionRecordModel::with(['product', 'batch'])
            ->whereIn('id', $this->consumptionRecordIds)
            ->get();

        $items = $records->map(fn (ConsumptionRecordModel $r) => [
            'product_name' => $r->product->name ?? 'N/A',
            'product_code' => $r->product->code ?? 'N/A',
            'quantity'     => $r->quantity,
            'batch_code'   => $r->batch?->lot_number ?? 'N/A',
            'expiry_date'  => $r->batch?->expiration_date?->toDateString() ?? 'N/A',
        ])->toArray();

        try {
            $result = $clinicalService->registerConsumption(
                $this->patientId,
                $this->appointmentId,
                $items,
            );

            if ($result['success']) {
                ConsumptionRecordModel::whereIn('id', $this->consumptionRecordIds)
                    ->update([
                        'sync_status'     => 'synced',
                        'synced_to_hc_at' => now(),
                    ]);

                Log::info("Consumo sincronizado con HC. Cita: {$this->appointmentId}");

                return;
            }

            throw new \RuntimeException("HC respondió con error: {$result['message']}");
        } catch (\Throwable $e) {
            ConsumptionRecordModel::whereIn('id', $this->consumptionRecordIds)
                ->update(['sync_status' => 'failed']);

            Log::error("Error al sincronizar consumo con HC: {$e->getMessage()}");

            throw $e;
        }
    }
}
