<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Services\KitExplosionService;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
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
     * Registers an exit of a kit product, deducting its components from stock.
     *
     * The methodology applied to each component mirrors exactly the simple-product
     * exit flow (FEFOService → BatchLocationService → StockMovementModel →
     * StockMovementCreated → StockBelowReorderPoint), with the sole addition of
     * a reference to the parent KitTransaction for full end-to-end traceability.
     *
     * One StockMovement is created per lot consumed per component. When FEFO
     * selects multiple lots to cover a component's required quantity, each lot
     * generates its own movement record so that lot-level audit trails are
     * preserved — the same behaviour expected in a pharmacy context.
     *
     * Flow:
     *  1. Validate product exists and is of type Kit.
     *  2. Pre-check kit availability via KitAvailabilityService (before any DB
     *     write) to surface a descriptive error before the transaction opens.
     *  3. Open a DB transaction and create the KitTransaction header.
     *  4. Explode the kit into component quantities (KitExplosionService).
     *  5. For each component apply FEFO, deduct each selected lot, and persist
     *     one StockMovement per lot, all referencing the kit_transaction.
     *  6. Dispatch StockMovementCreated per lot so stock_summaries stays current.
     *  7. Dispatch StockBelowReorderPoint per component when applicable.
     *
     * @param array{
     *     product_id: int,
     *     warehouse_id: int,
     *     quantity: int,
     *     user_id: int,
     *     cost_center_id: int,
     *     location_id?: int|null,
     *     reason?: string|null,
     *     service_id?: int|null,
     *     patient_document?: string|null,
     *     patient_external_id?: string|null,

     * } $data
     *
     * @return array{kit_transaction: KitTransactionModel, movements: Collection<int, StockMovementModel>}
     *
     * @throws ProductNotFoundException   When the product does not exist.
     * @throws \DomainException           When the product is not a kit or has no active components.
     * @throws InsufficientStockException When the warehouse cannot supply the requested kit quantity.
     */
    public function execute(array $data): array
    {
        $quantityKits = (int) $data['quantity'];

        $product = ProductModel::find($data['product_id']);

        if ($product === null) {
            throw new ProductNotFoundException("Producto {$data['product_id']} no encontrado.");
        }

        if ($product->product_type !== ProductType::Kit->value) {
            throw new \DomainException('El producto no es un kit.');
        }

        // Pre-check runs outside the transaction so no partial DB writes occur
        // when the requested quantity simply cannot be fulfilled.
        $this->assertKitIsAvailable($product, $data['warehouse_id'], $quantityKits);

        return DB::transaction(function () use ($data, $product, $quantityKits) {
            $kitTransaction = KitTransactionModel::create([
                'kit_product_id' => $data['product_id'],
                'warehouse_id'   => $data['warehouse_id'],
                'quantity_kits'  => $quantityKits,
                'user_id'        => $data['user_id'],
                'reason'         => $data['reason'] ?? null,
            ]);

            $components = $this->kitExplosionService->explode($data['product_id'], $quantityKits);
            $movements  = collect();

            foreach ($components as $component) {
                $componentMovements = $this->exitComponent($component, $kitTransaction, $data, $product->code);
                $movements = $movements->concat($componentMovements);

                $this->checkReorderPoint($component['component_product_id'], $data['warehouse_id']);
            }

            return [
                'kit_transaction' => $kitTransaction,
                'movements'       => $movements,
            ];
        });
    }

    /**
     * Throws InsufficientStockException with a descriptive message when the
     * warehouse does not have enough component stock to assemble the requested
     * number of kits. Uses stock_summaries (fast, eventually consistent) as a
     * first-pass guard before the authoritative FEFO check runs inside the
     * transaction.
     *
     * @throws InsufficientStockException
     */
    private function assertKitIsAvailable(ProductModel $product, int $warehouseId, int $quantityKits): void
    {
        $available = $this->kitAvailabilityService->getAvailableKits($product->id, $warehouseId);

        if ($available < $quantityKits) {
            throw new InsufficientStockException(
                "Stock de kit insuficiente. Se solicitan {$quantityKits} unidad(es) del kit "
                . "«{$product->name}» pero el almacén solo puede proporcionar {$available}."
            );
        }
    }

    /**
     * Applies FEFO to a single kit component, deducts each selected lot from
     * inventory, and creates one StockMovement per lot consumed. All movements
     * carry a reference to the parent KitTransaction.
     *
     * @param array{component_product_id: int, component_code: string, component_name: string, quantity_base: int} $component
     * @return Collection<int, StockMovementModel>
     */
    private function exitComponent(
        array $component,
        KitTransactionModel $kitTransaction,
        array $data,
        string $kitCode,
    ): Collection {
        $selectedBatches = $this->fefoService->selectBatchesForExit(
            $component['component_product_id'],
            $data['warehouse_id'],
            $component['quantity_base'],
        );

        $movements = collect();

        foreach ($selectedBatches as $selection) {
            $this->deductFromBatch($selection, $data['location_id'] ?? null);

            $movement = StockMovementModel::create([
                'warehouse_id'        => $data['warehouse_id'],
                'product_id'          => $component['component_product_id'],
                'batch_id'            => $selection['batch_id'],
                'location_from_id'    => $data['location_id'] ?? null,
                'movement_type'       => 'exit',
                'quantity'            => -$selection['quantity'],
                'reason'              => $data['reason'] ?? "Salida kit {$kitCode}",
                'reference_type'      => 'kit_transaction',
                'reference_id'        => $kitTransaction->id,
                'cost_center_id'      => $data['cost_center_id'],
                'service_id'          => $data['service_id'] ?? null,
                'patient_document'    => $data['patient_document'] ?? null,
                'patient_external_id' => $data['patient_external_id'] ?? null,

                'user_id'             => $data['user_id'],
            ]);

            event(new StockMovementCreated(
                movementId:   $movement->id,
                productId:    $component['component_product_id'],
                warehouseId:  $data['warehouse_id'],
                movementType: 'exit',
                quantity:     $selection['quantity'],
            ));

            $movements->push($movement);
        }

        return $movements;
    }

    /**
     * Dispatches StockBelowReorderPoint when the component's remaining stock
     * falls at or below its configured reorder point, matching exactly the
     * behaviour of RegisterExitUseCase for simple products.
     */
    private function checkReorderPoint(int $productId, int $warehouseId): void
    {
        $component = ProductModel::find($productId);

        if ($component === null || $component->reorder_point <= 0) {
            return;
        }

        $currentStock = $this->stockCalculator->getCurrentStock($productId, $warehouseId);

        if ($currentStock <= $component->reorder_point) {
            event(new StockBelowReorderPoint(
                productId:    $productId,
                warehouseId:  $warehouseId,
                currentStock: $currentStock,
                reorderPoint: $component->reorder_point,
            ));
        }
    }

    /**
     * Deducts $selection['quantity'] from the batch's available stock and marks
     * the batch as depleted when its quantity reaches zero. Also updates the
     * batch_location pivot to keep location-level stock consistent with the
     * stock_summaries recalculation done by UpdateStockSummary.
     *
     * @param array{batch_id: int, lot_number: string, quantity: int, expiration_date: string} $selection
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
