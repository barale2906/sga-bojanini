<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Services\BatchLocationService;
use App\Modules\Inventory\Domain\Services\DocumentNumberGenerator;
use App\Modules\Inventory\Domain\Services\FEFOService;
use App\Modules\Inventory\Domain\ValueObjects\MovementStatus;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Facades\DB;

class TransferStockUseCase
{
    public function __construct(
        private readonly FEFOService $fefoService,
        private readonly BatchLocationService $batchLocationService,
        private readonly DocumentNumberGenerator $numberGenerator,
    ) {}

    /**
     * Crea un documento de traslado con líneas pendientes de firma.
     * $data['items'] = [['product_variant_id'=>, 'location_from_id'=>, 'location_to_id'=>, 'quantity'=>], ...]
     */
    public function execute(array $data): MovementDocumentModel
    {
        return DB::transaction(function () use ($data) {
            $document = MovementDocumentModel::create([
                'document_number' => $this->numberGenerator->next('transfer'),
                'document_type'   => 'transfer',
                'warehouse_id'    => $data['warehouse_from_id'],
                'warehouse_to_id' => $data['warehouse_to_id'],
                'reason'          => $data['reason'] ?? null,
                'user_id'         => $data['user_id'],
                'status'          => MovementStatus::PENDING_SIGNATURE->value,
            ]);

            foreach ($data['items'] as $item) {
                $selectedBatches = $this->fefoService->selectBatchesForExit(
                    $item['product_variant_id'],
                    $data['warehouse_from_id'],
                    $item['quantity'],
                );

                foreach ($selectedBatches as $selection) {
                    StockMovementModel::create([
                        'movement_document_id' => $document->id,
                        'warehouse_id'         => $data['warehouse_from_id'],
                        'warehouse_to_id'      => $data['warehouse_to_id'],
                        'product_variant_id'   => $item['product_variant_id'],
                        'batch_id'             => $selection['batch_id'],
                        'location_from_id'     => $item['location_from_id'],
                        'location_to_id'       => $item['location_to_id'],
                        'movement_type'        => 'transfer',
                        'quantity'             => $selection['quantity'],
                        'reason'               => $data['reason'] ?? null,
                        'user_id'              => $data['user_id'],
                        'status'               => MovementStatus::PENDING_SIGNATURE->value,
                    ]);
                }
            }

            return $document->load(['movements.variant.genericProduct', 'movements.batch', 'warehouse', 'warehouseTo', 'user']);
        });
    }

    /** Aplica los cambios de ubicación del lote. Llamado desde ConfirmMovementUseCase. */
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
                movementId:       $movement->id,
                productVariantId: $movement->product_variant_id,
                warehouseId:      $movement->warehouse_id,
                movementType:     'transfer',
                quantity:         $quantity,
                warehouseToId:    $movement->warehouse_to_id,
            ));
        });
    }
}
