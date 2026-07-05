<?php

declare(strict_types=1);

namespace App\Modules\Integration\Application\UseCases;

use App\Modules\Integration\Infrastructure\Jobs\SyncConsumptionToHCJob;
use App\Modules\Integration\Infrastructure\Persistence\Models\ConsumptionRecordModel;
use App\Modules\Inventory\Application\UseCases\RegisterExitUseCase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RegisterConsumptionUseCase
{
    public function __construct(
        private readonly RegisterExitUseCase $exitUseCase,
    ) {}

    /**
     * @param array{
     *     appointment_id?: string,
     *     patient_identifier?: string,
     *     service_type?: string,
     *     warehouse_id: int,

     *     items: array<array{product_id: int, quantity: int}>
     * } $data
     *
     * @return array{records: array<int, ConsumptionRecordModel>, total_items: int}
     */
    public function execute(array $data): array
    {
        $records = [];

        DB::beginTransaction();

        try {
            foreach ($data['items'] as $item) {
                $exitResult = $this->exitUseCase->execute([
                    'product_id'          => $item['product_id'],
                    'warehouse_id'        => $data['warehouse_id'],
                    'quantity'            => $item['quantity'],
                    'reason'              => 'Consumo en procedimiento',
                    'cost_center_id'      => $data['cost_center_id'],
                    'service_id'          => $data['service_id'] ?? null,
                    // patient_identifier del registro de consumo mapea al documento en el movimiento
                    'patient_document'    => $data['patient_identifier'] ?? null,
                    // appointment_id actúa como identificador del paciente en el sistema externo
                    'patient_external_id' => $data['appointment_id'] ?? null,

                    'user_id'             => Auth::id(),
                ]);

                $batchId = $this->resolveBatchId($exitResult);

                $record = ConsumptionRecordModel::create([
                    'appointment_id'     => $data['appointment_id'] ?? null,
                    'patient_identifier' => $data['patient_identifier'] ?? null,
                    'service_type'       => $data['service_type'] ?? null,
                    'product_id'         => $item['product_id'],
                    'batch_id'           => $batchId,
                    'quantity'           => $item['quantity'],
                    'user_id'            => Auth::id(),
                    'consumed_at'        => now(),
                    'sync_status'        => 'pending',
                ]);

                $records[] = $record;
            }

            DB::commit();

            if (!empty($data['appointment_id']) && !empty($data['patient_identifier'])) {
                SyncConsumptionToHCJob::dispatch(
                    $data['patient_identifier'],
                    $data['appointment_id'],
                    array_map(fn (ConsumptionRecordModel $r) => $r->id, $records),
                );
            }

            return [
                'records'     => $records,
                'total_items' => count($records),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function resolveBatchId(Collection|array $exitResult): ?int
    {
        if ($exitResult instanceof Collection) {
            return $exitResult->first()?->batch_id;
        }

        /** @var Collection $movements */
        $movements = $exitResult['movements'] ?? collect();

        return $movements->first()?->batch_id;
    }
}
