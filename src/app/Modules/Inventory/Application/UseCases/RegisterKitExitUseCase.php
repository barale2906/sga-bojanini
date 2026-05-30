<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Services\KitExplosionService;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Inventory\Domain\Services\FEFOService;
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
    ) {}

    /**
     * @return array{kit_transaction: KitTransactionModel, movements: Collection<int, StockMovementModel>}
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $product = ProductModel::find($data['product_id']);

            if ($product === null) {
                throw new ProductNotFoundException("Producto {$data['product_id']} no encontrado.");
            }

            if ($product->product_type !== ProductType::Kit->value) {
                throw new \DomainException('El producto no es un kit.');
            }

            $quantityKits = (int) $data['quantity'];

            $kitTransaction = KitTransactionModel::create([
                'kit_product_id' => $data['product_id'],
                'warehouse_id'   => $data['warehouse_id'],
                'quantity_kits'    => $quantityKits,
                'user_id'          => $data['user_id'],
                'reason'           => $data['reason'] ?? null,
            ]);

            $components = $this->kitExplosionService->explode($data['product_id'], $quantityKits);
            $movements = collect();

            foreach ($components as $component) {
                $selectedBatches = $this->fefoService->selectBatchesForExit(
                    $component['component_product_id'],
                    $data['warehouse_id'],
                    $component['quantity_base'],
                );

                foreach ($selectedBatches as $selection) {
                    $this->deductFromBatch($selection, $data['location_id'] ?? null);
                }

                $movement = StockMovementModel::create([
                    'warehouse_id'        => $data['warehouse_id'],
                    'product_id'          => $component['component_product_id'],
                    'batch_id'            => $selectedBatches[0]['batch_id'],
                    'location_from_id'    => $data['location_id'] ?? null,
                    'movement_type'       => 'exit',
                    'quantity'            => -$component['quantity_base'],
                    'reason'              => $data['reason'] ?? "Salida kit {$product->code}",
                    'reference_type'      => 'kit_transaction',
                    'reference_id'        => $kitTransaction->id,
                    'cost_center_id'      => $data['cost_center_id'],
                    'service_id'          => $data['service_id'] ?? null,
                    'patient_document'    => $data['patient_document'] ?? null,
                    'patient_external_id' => $data['patient_external_id'] ?? null,
                    'user_id'             => $data['user_id'],
                ]);

                event(new StockMovementCreated(
                    movementId: $movement->id,
                    productId: $component['component_product_id'],
                    warehouseId: $data['warehouse_id'],
                    movementType: 'exit',
                    quantity: $component['quantity_base'],
                ));

                $movements->push($movement);
            }

            return [
                'kit_transaction' => $kitTransaction,
                'movements'       => $movements,
            ];
        });
    }

    /**
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

        if ($locationId !== null) {
            DB::table('batch_location')
                ->where('batch_id', $batch->id)
                ->where('location_id', $locationId)
                ->decrement('quantity', $selection['quantity']);
        }
    }
}
