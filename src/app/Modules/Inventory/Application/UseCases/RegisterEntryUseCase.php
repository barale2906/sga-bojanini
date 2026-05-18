<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Services\PresentationConverter;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use Illuminate\Support\Facades\DB;

class RegisterEntryUseCase
{
    public function __construct(
        private readonly PresentationConverter $presentationConverter,
    ) {}

    public function execute(array $data): StockMovementModel
    {
        return DB::transaction(function () use ($data) {
            $product = ProductModel::find($data['product_id']);

            if ($product === null) {
                throw new ProductNotFoundException(
                    "Producto con ID {$data['product_id']} no encontrado."
                );
            }

            if ($product->product_type === ProductType::Kit->value) {
                throw new \DomainException('No se pueden registrar entradas directas de productos tipo kit.');
            }

            $quantity = $this->resolveQuantity($data);

            $batch = BatchModel::where('product_id', $data['product_id'])
                ->where('lot_number', $data['lot_number'])
                ->first();

            if ($batch) {
                $batch->quantity_received += $quantity;
                $batch->quantity_available += $quantity;
                $batch->status = 'active';
                $batch->save();
            } else {
                $batch = BatchModel::create([
                    'product_id'         => $data['product_id'],
                    'lot_number'         => $data['lot_number'],
                    'expiration_date'    => $data['expiration_date'],
                    'manufacturing_date' => $data['manufacturing_date'] ?? null,
                    'quantity_received'  => $quantity,
                    'quantity_available' => $quantity,
                    'status'             => 'active',
                    'notes'              => $data['notes'] ?? null,
                    'received_at'        => now(),
                ]);
            }

            LocationModel::findOrFail($data['location_id']);

            $pivotRecord = DB::table('batch_location')
                ->where('batch_id', $batch->id)
                ->where('location_id', $data['location_id'])
                ->first();

            if ($pivotRecord) {
                DB::table('batch_location')
                    ->where('batch_id', $batch->id)
                    ->where('location_id', $data['location_id'])
                    ->update([
                        'quantity'   => $pivotRecord->quantity + $quantity,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('batch_location')->insert([
                    'batch_id'    => $batch->id,
                    'location_id' => $data['location_id'],
                    'quantity'    => $quantity,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            $movement = StockMovementModel::create([
                'warehouse_id'   => $data['warehouse_id'],
                'product_id'     => $data['product_id'],
                'batch_id'       => $batch->id,
                'location_to_id' => $data['location_id'],
                'movement_type'  => 'entry',
                'quantity'       => $quantity,
                'reason'         => $data['notes'] ?? null,
                'user_id'        => $data['user_id'],
            ]);

            event(new StockMovementCreated(
                movementId: $movement->id,
                productId: $data['product_id'],
                warehouseId: $data['warehouse_id'],
                movementType: 'entry',
                quantity: $quantity,
            ));

            return $movement;
        });
    }

    private function resolveQuantity(array $data): int
    {
        if (isset($data['quantity_base'])) {
            $quantity = (int) $data['quantity_base'];

            if ($quantity < 1) {
                throw new \DomainException('La cantidad debe ser mayor a cero.');
            }

            return $quantity;
        }

        if (isset($data['product_presentation_id'], $data['quantity_in_presentation'])) {
            return $this->presentationConverter->toBase(
                (int) $data['product_presentation_id'],
                (int) $data['quantity_in_presentation'],
            );
        }

        throw new \DomainException('Debe indicar quantity_base o product_presentation_id con quantity_in_presentation.');
    }
}
