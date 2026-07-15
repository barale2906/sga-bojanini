<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Services\KitExplosionService;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Inventory\Domain\Events\StockBelowReorderPoint;
use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Inventory\Domain\Services\BatchLocationService;
use App\Modules\Inventory\Domain\Services\FEFOService;
use App\Modules\Inventory\Domain\Services\KitAvailabilityService;
use App\Modules\Inventory\Domain\Services\StockCalculator;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\KitTransactionModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RegisterKitExitUseCase
{
    public function __construct(
        private readonly KitExplosionService $kitExplosionService,
        private readonly FEFOService $fefoService,
        private readonly BatchLocationService $batchLocationService,
        private readonly KitAvailabilityService $kitAvailabilityService,
        private readonly StockCalculator $stockCalculator,
    ) {}

    /**
     * Registra la salida de un kit dentro de un documento ya creado.
     * Llamado desde RegisterExitUseCase cuando el item es de tipo Kit.
     *
     * @throws ProductNotFoundException
     * @throws \DomainException
     * @throws InsufficientStockException
     */
    public function executeForDocument(array $item, array $data, MovementDocumentModel $document): void
    {
        $quantityKits = (int) $item['quantity'];

        $generic = GenericProductModel::find($item['generic_product_id']);

        if ($generic === null) {
            throw new ProductNotFoundException("Producto genérico {$item['generic_product_id']} no encontrado.");
        }

        if ($generic->product_type !== ProductType::Kit->value) {
            throw new \DomainException('El producto no es un kit.');
        }

        $this->assertKitIsAvailable($generic, $data['warehouse_id'], $quantityKits);

        $kitTransaction = KitTransactionModel::create([
            'kit_generic_id' => $item['generic_product_id'],
            'warehouse_id'   => $data['warehouse_id'],
            'quantity_kits'  => $quantityKits,
            'user_id'        => $data['user_id'],
            'reason'         => $data['reason'] ?? null,
        ]);

        $components = $this->kitExplosionService->explode($item['generic_product_id'], $quantityKits);

        foreach ($components as $component) {
            $this->exitComponent($component, $kitTransaction, $data, $document, $generic->barcode ?? $generic->name);
            $this->checkReorderPoint($component['component_generic_id'], $data['warehouse_id']);
        }
    }

    private function assertKitIsAvailable(GenericProductModel $generic, int $warehouseId, int $quantityKits): void
    {
        $available = $this->kitAvailabilityService->getAvailableKits($generic->id, $warehouseId);

        if ($available < $quantityKits) {
            throw new InsufficientStockException(
                "Stock de kit insuficiente. Se solicitan {$quantityKits} unidad(es) del kit "
                . "«{$generic->name}» pero el almacén solo puede proporcionar {$available}."
            );
        }
    }

    /**
     * @param array{component_generic_id: int, component_barcode: string, component_name: string, quantity_base: int} $component
     */
    private function exitComponent(
        array $component,
        KitTransactionModel $kitTransaction,
        array $data,
        MovementDocumentModel $document,
        string $kitRef,
    ): void {
        $selectedBatches = $this->fefoService->selectBatchesForGenericExit(
            $component['component_generic_id'],
            $data['warehouse_id'],
            $component['quantity_base'],
        );

        foreach ($selectedBatches as $selection) {
            $this->deductFromBatch($selection, $data['location_id'] ?? null);

            $movement = StockMovementModel::create([
                'movement_document_id' => $document->id,
                'warehouse_id'         => $data['warehouse_id'],
                'product_variant_id'   => $selection['product_variant_id'],
                'batch_id'             => $selection['batch_id'],
                'location_from_id'     => $data['location_id'] ?? null,
                'movement_type'        => 'exit',
                'quantity'             => -$selection['quantity'],
                'reason'               => $data['reason'] ?? "Salida kit {$kitRef}",
                'reference_type'       => 'kit_transaction',
                'reference_id'         => $kitTransaction->id,
                'cost_center_id'       => $data['cost_center_id'],
                'service_id'           => $data['service_id'] ?? null,
                'patient_document'     => $data['patient_document'] ?? null,
                'patient_external_id'  => $data['patient_external_id'] ?? null,
                'user_id'              => $data['user_id'],
            ]);

            event(new StockMovementCreated(
                movementId:       $movement->id,
                productVariantId: $selection['product_variant_id'],
                warehouseId:      $data['warehouse_id'],
                movementType:     'exit',
                quantity:         $selection['quantity'],
            ));
        }
    }

    private function checkReorderPoint(int $componentGenericId, int $warehouseId): void
    {
        $generic = GenericProductModel::find($componentGenericId);

        if ($generic === null || $generic->reorder_point <= 0) {
            return;
        }

        $currentStock = $this->stockCalculator->getGenericStock($componentGenericId, $warehouseId);

        if ($currentStock <= $generic->reorder_point) {
            event(new StockBelowReorderPoint(
                genericProductId: $componentGenericId,
                warehouseId:      $warehouseId,
                currentStock:     $currentStock,
                reorderPoint:     $generic->reorder_point,
            ));
        }
    }

    /**
     * @param array{batch_id: int, product_variant_id: int, lot_number: string, quantity: int, expiration_date: string} $selection
     */
    private function deductFromBatch(array $selection, ?int $locationId): void
    {
        $batch = BatchModel::findOrFail($selection['batch_id']);
        $batch->quantity_available -= $selection['quantity'];

        if ($batch->quantity_available <= 0) {
            $batch->quantity_available = 0;
            $batch->status = 'depleted';
        }

        $batch->save();

        $this->batchLocationService->decrement($batch->id, $selection['quantity'], $locationId);
    }
}
