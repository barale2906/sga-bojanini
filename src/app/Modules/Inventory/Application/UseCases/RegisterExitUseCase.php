<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;
use App\Modules\Inventory\Domain\Events\StockBelowReorderPoint;
use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Inventory\Domain\Services\BatchLocationService;
use App\Modules\Inventory\Domain\Services\DocumentNumberGenerator;
use App\Modules\Inventory\Domain\Services\FEFOService;
use App\Modules\Inventory\Domain\Services\StockCalculator;
use App\Modules\Inventory\Domain\ValueObjects\MovementStatus;
use App\Modules\Inventory\Domain\ValueObjects\MovementType;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Facades\DB;

class RegisterExitUseCase
{
    public function __construct(
        private readonly FEFOService $fefoService,
        private readonly StockCalculator $stockCalculator,
        private readonly RegisterKitExitUseCase $registerKitExitUseCase,
        private readonly BatchLocationService $batchLocationService,
        private readonly DocumentNumberGenerator $numberGenerator,
    ) {}

    /**
     * Procesa una salida con uno o más productos.
     * $data['items'] = [['generic_product_id'=>, 'quantity'=>, 'location_id'=>?], ...]
     *
     * @return MovementDocumentModel
     */
    public function execute(array $data): MovementDocumentModel
    {
        $this->validateCostCenter($data);

        $isPatientExit = ! empty($data['patient_document']);
        $requiresSignature = MovementType::EXIT->requiresSignature($isPatientExit);

        return DB::transaction(function () use ($data, $requiresSignature) {
            $document = MovementDocumentModel::create([
                'document_number'     => $this->numberGenerator->next('exit'),
                'document_type'       => 'exit',
                'warehouse_id'        => $data['warehouse_id'],
                'movement_date'       => $data['movement_date'] ?? now(),
                'cost_center_id'      => $data['cost_center_id'],
                'service_id'          => $data['service_id'] ?? null,
                'patient_document'    => $data['patient_document'] ?? null,
                'patient_external_id' => $data['patient_external_id'] ?? null,
                'reason'              => $data['reason'] ?? null,
                'user_id'             => $data['user_id'],
                'status'              => $requiresSignature
                    ? MovementStatus::PENDING_SIGNATURE->value
                    : MovementStatus::CONFIRMED->value,
            ]);

            foreach ($data['items'] as $item) {
                $generic = GenericProductModel::find($item['generic_product_id']);

                if ($generic === null) {
                    throw new ProductNotFoundException("Producto genérico {$item['generic_product_id']} no encontrado.");
                }

                if ($generic->product_type === ProductType::Kit->value) {
                    $this->registerKitExitUseCase->executeForDocument($item, $data, $document);
                } elseif ($requiresSignature) {
                    $this->createPendingLines($item, $data, $document);
                } else {
                    $this->applyStockAndCreateLines($item, $data, $document, $generic);
                }
            }

            return $document->load(['movements.variant.genericProduct', 'movements.batch', 'warehouse', 'costCenter', 'medicalService', 'user', 'signatures']);
        });
    }

    /** Crea líneas pendientes de firma (sin modificar stock). */
    private function createPendingLines(array $item, array $data, MovementDocumentModel $document): void
    {
        $selectedBatches = $this->fefoService->selectBatchesForGenericExit(
            $item['generic_product_id'],
            $data['warehouse_id'],
            $item['quantity'],
        );

        foreach ($selectedBatches as $selection) {
            StockMovementModel::create([
                'movement_document_id' => $document->id,
                'warehouse_id'         => $data['warehouse_id'],
                'product_variant_id'   => $selection['product_variant_id'],
                'batch_id'             => $selection['batch_id'],
                'location_from_id'     => $item['location_id'] ?? null,
                'movement_type'        => 'exit',
                'quantity'             => -$selection['quantity'],
                'reason'               => $data['reason'] ?? null,
                'movement_date'        => $document->movement_date,
                'cost_center_id'       => $data['cost_center_id'],
                'service_id'           => $data['service_id'] ?? null,
                'patient_document'     => $data['patient_document'] ?? null,
                'patient_external_id'  => $data['patient_external_id'] ?? null,
                'user_id'              => $data['user_id'],
                'status'               => MovementStatus::PENDING_SIGNATURE->value,
            ]);
        }
    }

    /** Aplica stock y crea líneas de forma directa (entrega a centro de costo interno). */
    private function applyStockAndCreateLines(array $item, array $data, MovementDocumentModel $document, GenericProductModel $generic): void
    {
        $selectedBatches = $this->fefoService->selectBatchesForGenericExit(
            $item['generic_product_id'],
            $data['warehouse_id'],
            $item['quantity'],
        );

        foreach ($selectedBatches as $selection) {
            $batch = BatchModel::findOrFail($selection['batch_id']);
            $batch->quantity_available -= $selection['quantity'];

            if ($batch->quantity_available <= 0) {
                $batch->quantity_available = 0;
                $batch->status = 'depleted';
            }

            $batch->save();

            $this->batchLocationService->decrement($batch->id, $selection['quantity'], $item['location_id'] ?? null);

            $movement = StockMovementModel::create([
                'movement_document_id' => $document->id,
                'warehouse_id'         => $data['warehouse_id'],
                'product_variant_id'   => $selection['product_variant_id'],
                'batch_id'             => $selection['batch_id'],
                'location_from_id'     => $item['location_id'] ?? null,
                'movement_type'        => 'exit',
                'quantity'             => -$selection['quantity'],
                'reason'               => $data['reason'] ?? null,
                'movement_date'        => $document->movement_date,
                'cost_center_id'       => $data['cost_center_id'],
                'service_id'           => $data['service_id'] ?? null,
                'patient_document'     => $data['patient_document'] ?? null,
                'patient_external_id'  => $data['patient_external_id'] ?? null,
                'user_id'              => $data['user_id'],
                'status'               => MovementStatus::CONFIRMED->value,
            ]);

            event(new StockMovementCreated(
                movementId:       $movement->id,
                productVariantId: $selection['product_variant_id'],
                warehouseId:      $data['warehouse_id'],
                movementType:     'exit',
                quantity:         $selection['quantity'],
            ));
        }

        $currentStock = $this->stockCalculator->getGenericStock($item['generic_product_id'], $data['warehouse_id']);

        if ($generic->reorder_point > 0 && $currentStock <= $generic->reorder_point) {
            event(new StockBelowReorderPoint(
                genericProductId: $item['generic_product_id'],
                warehouseId:      $data['warehouse_id'],
                currentStock:     $currentStock,
                reorderPoint:     $generic->reorder_point,
            ));
        }
    }

    /** Aplica el stock de todas las líneas de un documento confirmado. Llamado desde ConfirmMovementUseCase. */
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
                movementId:       $movement->id,
                productVariantId: $movement->product_variant_id,
                warehouseId:      $movement->warehouse_id,
                movementType:     'exit',
                quantity:         $quantity,
            ));

            $variant   = $movement->variant;
            $genericId = $variant->generic_product_id;
            $generic   = GenericProductModel::find($genericId);

            if ($generic && $generic->reorder_point > 0) {
                $currentStock = $this->stockCalculator->getGenericStock($genericId, $movement->warehouse_id);

                if ($currentStock <= $generic->reorder_point) {
                    event(new StockBelowReorderPoint(
                        genericProductId: $genericId,
                        warehouseId:      $movement->warehouse_id,
                        currentStock:     $currentStock,
                        reorderPoint:     $generic->reorder_point,
                    ));
                }
            }
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
