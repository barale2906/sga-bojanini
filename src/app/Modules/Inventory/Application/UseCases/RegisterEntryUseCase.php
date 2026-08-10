<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Services\PresentationConverter;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Inventory\Domain\Services\DocumentNumberGenerator;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\LocationModel;
use Illuminate\Support\Facades\DB;

class RegisterEntryUseCase
{
    public function __construct(
        private readonly PresentationConverter $presentationConverter,
        private readonly DocumentNumberGenerator $numberGenerator,
    ) {}

    public function execute(array $data): MovementDocumentModel
    {
        return DB::transaction(function () use ($data) {
            $document = MovementDocumentModel::create([
                'document_number'    => $this->numberGenerator->next('entry'),
                'document_type'      => 'entry',
                'warehouse_id'       => $data['warehouse_id'],
                'invoice_number'     => $data['invoice_number'] ?? null,
                'entry_temperature'  => isset($data['entry_temperature']) ? (float) $data['entry_temperature'] : null,
                'reason'             => $data['reason'] ?? null,
                'user_id'            => $data['user_id'],
                'status'             => 'confirmed',
            ]);

            foreach ($data['items'] as $item) {
                $this->processItem($item, $data['warehouse_id'], $data['user_id'], $document);
            }

            return $document->load(['movements.variant.genericProduct', 'movements.batch', 'warehouse', 'user']);
        });
    }

    private function processItem(array $item, int $warehouseId, int $userId, MovementDocumentModel $document): void
    {
        $variant = ProductVariantModel::find($item['product_variant_id']);

        if ($variant === null) {
            throw new ProductNotFoundException(
                "Variante de producto con ID {$item['product_variant_id']} no encontrada."
            );
        }

        if ($variant->genericProduct->product_type === ProductType::Kit->value) {
            throw new \DomainException('No se pueden registrar entradas directas de productos tipo kit.');
        }

        $quantity = $this->resolveQuantity($item);

        $batch = BatchModel::where('product_variant_id', $item['product_variant_id'])
            ->where('lot_number', $item['lot_number'])
            ->first();

        if ($batch) {
            $batch->quantity_received += $quantity;
            $batch->quantity_available += $quantity;
            $batch->status = 'active';
            $batch->save();
        } else {
            $batch = BatchModel::create([
                'product_variant_id' => $item['product_variant_id'],
                'lot_number'         => $item['lot_number'],
                'expiration_date'    => $item['expiration_date'],
                'manufacturing_date' => $item['manufacturing_date'] ?? null,
                'quantity_received'  => $quantity,
                'quantity_available' => $quantity,
                'status'             => 'active',
                'notes'              => $item['notes'] ?? null,
                'received_at'        => now(),
            ]);
        }

        LocationModel::findOrFail($item['location_id']);

        $pivotRecord = DB::table('batch_location')
            ->where('batch_id', $batch->id)
            ->where('location_id', $item['location_id'])
            ->first();

        if ($pivotRecord) {
            DB::table('batch_location')
                ->where('batch_id', $batch->id)
                ->where('location_id', $item['location_id'])
                ->update([
                    'quantity'   => $pivotRecord->quantity + $quantity,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('batch_location')->insert([
                'batch_id'    => $batch->id,
                'location_id' => $item['location_id'],
                'quantity'    => $quantity,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        $movement = StockMovementModel::create([
            'movement_document_id' => $document->id,
            'warehouse_id'         => $warehouseId,
            'product_variant_id'   => $item['product_variant_id'],
            'batch_id'             => $batch->id,
            'location_to_id'       => $item['location_id'],
            'movement_type'        => 'entry',
            'quantity'             => $quantity,
            'reason'               => $item['notes'] ?? null,
            'invoice_number'       => $document->invoice_number,
            'entry_temperature'    => $document->entry_temperature,
            'user_id'              => $userId,
        ]);

        event(new StockMovementCreated(
            movementId:       $movement->id,
            productVariantId: $item['product_variant_id'],
            warehouseId:      $warehouseId,
            movementType:     'entry',
            quantity:         $quantity,
        ));
    }

    private function resolveQuantity(array $item): float
    {
        if (isset($item['quantity_base'])) {
            $quantity = (float) $item['quantity_base'];

            if ($quantity <= 0) {
                throw new \DomainException('La cantidad debe ser mayor a cero.');
            }

            return $quantity;
        }

        if (isset($item['product_presentation_id'], $item['quantity_in_presentation'])) {
            return $this->presentationConverter->toBase(
                (int) $item['product_presentation_id'],
                (float) $item['quantity_in_presentation'],
            );
        }

        throw new \DomainException('Debe indicar quantity_base o product_presentation_id con quantity_in_presentation.');
    }
}
