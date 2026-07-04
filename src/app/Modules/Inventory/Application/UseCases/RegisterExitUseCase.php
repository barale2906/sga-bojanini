<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;
use App\Modules\Inventory\Domain\Events\StockBelowReorderPoint;
use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Inventory\Domain\Services\BatchLocationService;
use App\Modules\Inventory\Domain\Services\FEFOService;
use App\Modules\Inventory\Domain\Services\StockCalculator;
use App\Modules\Inventory\Domain\ValueObjects\MovementStatus;
use App\Modules\Inventory\Domain\ValueObjects\MovementType;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RegisterExitUseCase
{
    public function __construct(
        private readonly FEFOService $fefoService,
        private readonly StockCalculator $stockCalculator,
        private readonly RegisterKitExitUseCase $registerKitExitUseCase,
        private readonly BatchLocationService $batchLocationService,
    ) {}

    /**
     * @return Collection<int, StockMovementModel>|array{kit_transaction: \App\Modules\Inventory\Infrastructure\Persistence\Models\KitTransactionModel, movements: Collection<int, StockMovementModel>}
     */
    public function execute(array $data): Collection|array
    {
        $product = ProductModel::find($data['product_id']);

        if ($product === null) {
            throw new ProductNotFoundException("Producto {$data['product_id']} no encontrado.");
        }

        $this->validateCostCenter($data);

        if ($product->product_type === ProductType::Kit->value) {
            return $this->registerKitExitUseCase->execute($data);
        }

        $isPatientExit = ! empty($data['patient_document']);

        if (MovementType::EXIT->requiresSignature($isPatientExit)) {
            return $this->createPending($data);
        }

        return $this->applyStockAndCreate($data);
    }

    /**
     * Crea registros pendientes de firma. Corre FEFO para validar disponibilidad
     * y asignar lotes desde el inicio; no modifica stock.
     *
     * @return Collection<int, StockMovementModel>
     */
    public function createPending(array $data): Collection
    {
        return DB::transaction(function () use ($data) {
            $selectedBatches = $this->fefoService->selectBatchesForExit(
                $data['product_id'],
                $data['warehouse_id'],
                $data['quantity'],
            );

            $movements = collect();

            foreach ($selectedBatches as $selection) {
                $movement = StockMovementModel::create([
                    'warehouse_id'        => $data['warehouse_id'],
                    'product_id'          => $data['product_id'],
                    'batch_id'            => $selection['batch_id'],
                    'location_from_id'    => $data['location_id'] ?? null,
                    'movement_type'       => 'exit',
                    'quantity'            => -$selection['quantity'],
                    'reason'              => $data['reason'] ?? null,
                    'cost_center_id'      => $data['cost_center_id'],
                    'service_id'          => $data['service_id'] ?? null,
                    'patient_document'    => $data['patient_document'] ?? null,
                    'patient_external_id' => $data['patient_external_id'] ?? null,
                    'user_id'             => $data['user_id'],
                    'status'              => MovementStatus::PENDING_SIGNATURE->value,
                ]);

                $movements->push($movement);
            }

            return $movements;
        });
    }

    /** Aplica el stock del lote ya asignado en el registro pendiente. */
    public function applyStock(StockMovementModel $movement): void
    {
        DB::transaction(function () use ($movement) {
            $quantity = abs($movement->quantity);
            $batch    = BatchModel::findOrFail($movement->batch_id);

            $batch->quantity_available -= $quantity;

            if ($batch->quantity_available <= 0) {
                $batch->quantity_available = 0;
                $batch->status = 'depleted';
            }

            $batch->save();

            $this->batchLocationService->decrement($batch->id, $quantity, $movement->location_from_id);

            event(new StockMovementCreated(
                movementId: $movement->id,
                productId: $movement->product_id,
                warehouseId: $movement->warehouse_id,
                movementType: 'exit',
                quantity: $quantity,
            ));

            $product = ProductModel::findOrFail($movement->product_id);
            $currentStock = $this->stockCalculator->getCurrentStock(
                $movement->product_id,
                $movement->warehouse_id,
            );

            if ($product->reorder_point > 0 && $currentStock <= $product->reorder_point) {
                event(new StockBelowReorderPoint(
                    productId: $movement->product_id,
                    warehouseId: $movement->warehouse_id,
                    currentStock: $currentStock,
                    reorderPoint: $product->reorder_point,
                ));
            }
        });
    }

    /** Flujo directo para salidas a pacientes (sin firma): aplica stock y crea movimiento en un paso. */
    private function applyStockAndCreate(array $data): Collection
    {
        return DB::transaction(function () use ($data) {
            $selectedBatches = $this->fefoService->selectBatchesForExit(
                $data['product_id'],
                $data['warehouse_id'],
                $data['quantity'],
            );

            $movements = collect();
            $product   = ProductModel::findOrFail($data['product_id']);

            foreach ($selectedBatches as $selection) {
                $batch = BatchModel::findOrFail($selection['batch_id']);
                $batch->quantity_available -= $selection['quantity'];

                if ($batch->quantity_available <= 0) {
                    $batch->quantity_available = 0;
                    $batch->status = 'depleted';
                }

                $batch->save();

                $this->batchLocationService->decrement($batch->id, $selection['quantity'], $data['location_id'] ?? null);

                $movement = StockMovementModel::create([
                    'warehouse_id'        => $data['warehouse_id'],
                    'product_id'          => $data['product_id'],
                    'batch_id'            => $selection['batch_id'],
                    'location_from_id'    => $data['location_id'] ?? null,
                    'movement_type'       => 'exit',
                    'quantity'            => -$selection['quantity'],
                    'reason'              => $data['reason'] ?? null,
                    'cost_center_id'      => $data['cost_center_id'],
                    'service_id'          => $data['service_id'] ?? null,
                    'patient_document'    => $data['patient_document'] ?? null,
                    'patient_external_id' => $data['patient_external_id'] ?? null,
                    'user_id'             => $data['user_id'],
                    'status'              => MovementStatus::CONFIRMED->value,
                ]);

                event(new StockMovementCreated(
                    movementId: $movement->id,
                    productId: $data['product_id'],
                    warehouseId: $data['warehouse_id'],
                    movementType: 'exit',
                    quantity: $selection['quantity'],
                ));

                $movements->push($movement);
            }

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

            return $movements;
        });
    }

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
