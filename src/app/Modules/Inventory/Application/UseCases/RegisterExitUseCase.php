<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;
use App\Modules\Inventory\Domain\Events\StockBelowReorderPoint;
use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Inventory\Domain\Services\FEFOService;
use App\Modules\Inventory\Domain\Services\StockCalculator;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Facades\DB;

class RegisterExitUseCase
{
    public function __construct(
        private readonly FEFOService $fefoService,
        private readonly StockCalculator $stockCalculator,
        private readonly RegisterKitExitUseCase $registerKitExitUseCase,
    ) {}

    /**
     * @return StockMovementModel|array{kit_transaction: \App\Modules\Inventory\Infrastructure\Persistence\Models\KitTransactionModel, movements: \Illuminate\Support\Collection}
     */
    public function execute(array $data): StockMovementModel|array
    {
        $product = ProductModel::find($data['product_id']);

        if ($product === null) {
            throw new ProductNotFoundException("Producto {$data['product_id']} no encontrado.");
        }

        $this->validateCostCenter($data);

        if ($product->product_type === ProductType::Kit->value) {
            return $this->registerKitExitUseCase->execute($data);
        }

        return DB::transaction(function () use ($data, $product) {
            $selectedBatches = $this->fefoService->selectBatchesForExit(
                $data['product_id'],
                $data['warehouse_id'],
                $data['quantity'],
            );

            foreach ($selectedBatches as $selection) {
                $batch = BatchModel::findOrFail($selection['batch_id']);
                $batch->quantity_available -= $selection['quantity'];

                if ($batch->quantity_available <= 0) {
                    $batch->quantity_available = 0;
                    $batch->status = 'depleted';
                }

                $batch->save();

                $this->decrementBatchLocations($batch->id, $selection['quantity'], $data['location_id'] ?? null);
            }

            $movement = StockMovementModel::create([
                'warehouse_id'        => $data['warehouse_id'],
                'product_id'          => $data['product_id'],
                'batch_id'            => $selectedBatches[0]['batch_id'],
                'location_from_id'    => $data['location_id'] ?? null,
                'movement_type'       => 'exit',
                'quantity'            => -$data['quantity'],
                'reason'              => $data['reason'] ?? null,
                'cost_center_id'      => $data['cost_center_id'],
                'service_id'          => $data['service_id'] ?? null,
                'patient_document'    => $data['patient_document'] ?? null,
                'patient_external_id' => $data['patient_external_id'] ?? null,
                'user_id'             => $data['user_id'],
            ]);

            event(new StockMovementCreated(
                movementId: $movement->id,
                productId: $data['product_id'],
                warehouseId: $data['warehouse_id'],
                movementType: 'exit',
                quantity: $data['quantity'],
            ));

            $currentStock = $this->stockCalculator->getCurrentStock(
                $data['product_id'],
                $data['warehouse_id'],
            );

            if ($product->reorder_point > 0 && $currentStock <= $product->reorder_point) {
                event(new StockBelowReorderPoint(
                    productId: $data['product_id'],
                    warehouseId: $data['warehouse_id'],
                    currentStock: $currentStock,
                    reorderPoint: $product->reorder_point,
                ));
            }

            return $movement;
        });
    }

    /**
     * Descuenta la cantidad consumida del lote en sus ubicaciones (batch_location).
     *
     * Si se indica una ubicación preferida, se descuenta primero de ella; el resto
     * se reparte entre las demás ubicaciones del lote hasta cubrir la cantidad.
     */
    private function decrementBatchLocations(int $batchId, int $quantity, ?int $preferredLocationId): void
    {
        $remaining = $quantity;

        if ($preferredLocationId !== null) {
            $pivot = DB::table('batch_location')
                ->where('batch_id', $batchId)
                ->where('location_id', $preferredLocationId)
                ->first();

            if ($pivot !== null) {
                $take = min($remaining, $pivot->quantity);

                DB::table('batch_location')
                    ->where('batch_id', $batchId)
                    ->where('location_id', $preferredLocationId)
                    ->decrement('quantity', $take);

                $remaining -= $take;
            }
        }

        if ($remaining <= 0) {
            return;
        }

        $pivots = DB::table('batch_location')
            ->where('batch_id', $batchId)
            ->where('quantity', '>', 0)
            ->when($preferredLocationId !== null, fn ($query) => $query->where('location_id', '!=', $preferredLocationId))
            ->orderBy('location_id')
            ->get();

        foreach ($pivots as $pivot) {
            if ($remaining <= 0) {
                break;
            }

            $take = min($remaining, $pivot->quantity);

            DB::table('batch_location')
                ->where('batch_id', $batchId)
                ->where('location_id', $pivot->location_id)
                ->decrement('quantity', $take);

            $remaining -= $take;
        }
    }

    /** Valida las reglas de negocio del centro de costo sobre los datos de salida. */
    private function validateCostCenter(array $data): void
    {
        $costCenterId = $data['cost_center_id'] ?? null;

        if (empty($costCenterId)) {
            throw new \DomainException('El centro de costo es obligatorio para registrar una salida de inventario.');
        }

        $costCenter = CostCenterModel::find($costCenterId);

        if ($costCenter === null || ! $costCenter->is_active) {
            throw new \DomainException('El centro de costo no existe o está inactivo.');
        }

        if ($costCenter->type === 'external') {
            if (empty($data['service_id'])) {
                throw new \DomainException('El servicio médico es obligatorio para salidas con centro de costo externo (pacientes).');
            }

            if (empty($data['patient_document'])) {
                throw new \DomainException('El número de documento del paciente es obligatorio para salidas con centro de costo externo.');
            }

            if (empty($data['patient_external_id'])) {
                throw new \DomainException('El ID del paciente en el sistema externo es obligatorio para salidas con centro de costo externo.');
            }
        }
    }
}
