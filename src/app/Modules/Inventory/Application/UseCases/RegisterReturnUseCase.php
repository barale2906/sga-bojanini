<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Services\BatchLocationService;
use App\Modules\Inventory\Domain\Services\FEFOService;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Facades\DB;

class RegisterReturnUseCase
{
    public function __construct(
        private readonly FEFOService $fefoService,
        private readonly BatchLocationService $batchLocationService,
    ) {}

    public function execute(array $data): StockMovementModel
    {
        return DB::transaction(function () use ($data) {
            // Las devoluciones a proveedor también gestionan stock vencido,
            // por lo que se incluyen lotes con expiration_date pasada.
            $selectedBatches = $this->fefoService->selectBatchesForExit(
                $data['product_id'],
                $data['warehouse_id'],
                $data['quantity'],
                includeExpired: true,
            );

            foreach ($selectedBatches as $selection) {
                $batch = BatchModel::findOrFail($selection['batch_id']);
                $batch->quantity_available -= $selection['quantity'];

                if ($batch->quantity_available <= 0) {
                    $batch->quantity_available = 0;
                    $batch->status = 'depleted';
                }

                $batch->save();

                $this->batchLocationService->decrement($batch->id, $selection['quantity'], $data['location_id'] ?? null);
            }

            $movement = StockMovementModel::create([
                'warehouse_id'     => $data['warehouse_id'],
                'product_id'       => $data['product_id'],
                'batch_id'         => $selectedBatches[0]['batch_id'],
                'location_from_id' => $data['location_id'] ?? null,
                'movement_type'    => 'return',
                'quantity'         => -$data['quantity'],
                'reason'           => $data['reason'] ?? 'Devolución a proveedor',
                'user_id'          => $data['user_id'],
            ]);

            event(new StockMovementCreated(
                movementId: $movement->id,
                productId: $data['product_id'],
                warehouseId: $data['warehouse_id'],
                movementType: 'return',
                quantity: $data['quantity'],
            ));

            return $movement;
        });
    }
}
