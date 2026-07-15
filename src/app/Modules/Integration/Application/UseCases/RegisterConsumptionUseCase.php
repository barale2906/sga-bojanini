<?php

declare(strict_types=1);

namespace App\Modules\Integration\Application\UseCases;

use App\Modules\Integration\Infrastructure\Jobs\SyncConsumptionToHCJob;
use App\Modules\Integration\Infrastructure\Persistence\Models\ConsumptionRecordModel;
use App\Modules\Inventory\Application\UseCases\RegisterExitUseCase;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
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
     *     items: array<array{generic_product_id: int, quantity: int}>
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
                    'warehouse_id'        => $data['warehouse_id'],
                    'reason'              => 'Consumo en procedimiento',
                    'cost_center_id'      => $data['cost_center_id'],
                    'service_id'          => $data['service_id'] ?? null,
                    'patient_document'    => $data['patient_identifier'] ?? null,
                    'patient_external_id' => $data['appointment_id'] ?? null,
                    'user_id'             => Auth::id(),
                    'items'               => [[
                        'generic_product_id' => $item['generic_product_id'],
                        'quantity'           => $item['quantity'],
                    ]],
                ]);

                $batchId   = $this->resolveBatchId($exitResult);
                $variantId = $this->resolveVariantId($exitResult);

                $record = ConsumptionRecordModel::create([
                    'appointment_id'     => $data['appointment_id'] ?? null,
                    'patient_identifier' => $data['patient_identifier'] ?? null,
                    'service_type'       => $data['service_type'] ?? null,
                    'product_variant_id' => $variantId,
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

    private function resolveBatchId(MovementDocumentModel $document): ?int
    {
        return $document->movements->first()?->batch_id;
    }

    private function resolveVariantId(MovementDocumentModel $document): ?int
    {
        return $document->movements->first()?->product_variant_id;
    }
}
