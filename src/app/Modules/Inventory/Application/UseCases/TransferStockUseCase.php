<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Services\BatchLocationService;
use App\Modules\Inventory\Domain\Services\FEFOService;
use App\Modules\Inventory\Domain\ValueObjects\MovementStatus;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransferStockUseCase
{
    public function __construct(
        private readonly FEFOService $fefoService,
        private readonly BatchLocationService $batchLocationService,
    ) {}

    public function execute(array $data): Collection
    {
        return $this->createPending($data);
    }

    /**
     * Crea registros pendientes de firma. Corre FEFO para validar disponibilidad
     * y asignar lotes desde el inicio; no modifica batch_location ni stock.
     *
     * @return Collection<int, StockMovementModel>
     */
    public function createPending(array $data): Collection
    {
        return DB::transaction(function () use ($data) {
            $selectedBatches = $this->fefoService->selectBatchesForExit(
                $data['product_id'],
                $data['warehouse_from_id'],
                $data['quantity'],
            );

            $movements = collect();

            foreach ($selectedBatches as $selection) {
                $movement = StockMovementModel::create([
                    'warehouse_id'     => $data['warehouse_from_id'],
                    'warehouse_to_id'  => $data['warehouse_to_id'],
                    'product_id'       => $data['product_id'],
                    'batch_id'         => $selection['batch_id'],
                    'location_from_id' => $data['location_from_id'],
                    'location_to_id'   => $data['location_to_id'],
                    'movement_type'    => 'transfer',
                    'quantity'         => $selection['quantity'],
                    'reason'           => $data['reason'] ?? null,
                    'user_id'          => $data['user_id'],
                    'status'           => MovementStatus::PENDING_SIGNATURE->value,
                ]);

                $movements->push($movement);
            }

            return $movements;
        });
    }

    /** Aplica los cambios de ubicación del lote ya asignado en el registro pendiente. */
    public function applyStock(StockMovementModel $movement): void
    {
        DB::transaction(function () use ($movement) {
            $quantity = $movement->quantity;

            $this->batchLocationService->decrement($movement->batch_id, $quantity, $movement->location_from_id);

            $pivot = DB::table('batch_location')
                ->where('batch_id', $movement->batch_id)
                ->where('location_id', $movement->location_to_id)
                ->first();

            if ($pivot) {
                DB::table('batch_location')
                    ->where('batch_id', $movement->batch_id)
                    ->where('location_id', $movement->location_to_id)
                    ->update([
                        'quantity'   => $pivot->quantity + $quantity,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('batch_location')->insert([
                    'batch_id'    => $movement->batch_id,
                    'location_id' => $movement->location_to_id,
                    'quantity'    => $quantity,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            event(new StockMovementCreated(
                movementId: $movement->id,
                productId: $movement->product_id,
                warehouseId: $movement->warehouse_id,
                movementType: 'transfer',
                quantity: $quantity,
                warehouseToId: $movement->warehouse_to_id,
            ));
        });
    }
}
