<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Services\BatchLocationService;
use App\Modules\Inventory\Domain\Services\DocumentNumberGenerator;
use App\Modules\Inventory\Domain\ValueObjects\BatchStatus;
use App\Modules\Inventory\Domain\ValueObjects\MovementStatus;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Facades\DB;

/**
 * Da de baja por vencimiento todo el stock de un lote dentro de UN almacén
 * (todas sus ubicaciones en ese almacén). El stock del mismo lote en otros
 * almacenes no se modifica.
 */
class WriteOffExpiredUseCase
{
    public function __construct(
        private readonly BatchLocationService $batchLocationService,
        private readonly DocumentNumberGenerator $numberGenerator,
    ) {}

    public function execute(array $data): StockMovementModel
    {
        return $this->createPending($data);
    }

    public function createPending(array $data): StockMovementModel
    {
        return DB::transaction(function () use ($data) {
            $batch       = BatchModel::findOrFail($data['batch_id']);
            $warehouseId = (int) $data['warehouse_id'];

            if (! empty($data['location_id'])) {
                $this->batchLocationService->assertLocationInWarehouse((int) $data['location_id'], $warehouseId);
            }

            $quantity = $this->batchLocationService->quantityInWarehouse($batch->id, $warehouseId);

            if ($quantity <= 0) {
                throw new \DomainException('El lote no tiene stock disponible en este almacén para dar de baja.');
            }

            $document = MovementDocumentModel::create([
                'document_number' => $this->numberGenerator->next('expiration_write_off'),
                'document_type'   => 'expiration_write_off',
                'warehouse_id'    => $warehouseId,
                'reason'          => $data['reason'] ?? 'Baja por vencimiento',
                'user_id'         => $data['user_id'],
                'status'          => MovementStatus::PENDING_SIGNATURE->value,
            ]);

            return StockMovementModel::create([
                'movement_document_id' => $document->id,
                'warehouse_id'         => $warehouseId,
                'product_variant_id'   => $batch->product_variant_id,
                'batch_id'             => $batch->id,
                'location_from_id'     => $data['location_id'] ?? null,
                'movement_type'        => 'expiration_write_off',
                'quantity'             => -$quantity,
                'reason'               => $data['reason'] ?? 'Baja por vencimiento',
                'user_id'              => $data['user_id'],
                'status'               => MovementStatus::PENDING_SIGNATURE->value,
            ]);
        });
    }

    public function applyStock(StockMovementModel $movement): void
    {
        DB::transaction(function () use ($movement) {
            $quantity = $this->batchLocationService->clearWarehouse($movement->batch_id, $movement->warehouse_id);

            if ($quantity <= 0) {
                throw new \DomainException('El lote no tiene stock disponible en este almacén para dar de baja.');
            }

            $batch = BatchModel::findOrFail($movement->batch_id);

            if ($batch->quantity_available <= 0) {
                $batch->status = BatchStatus::EXPIRED->value;
                $batch->save();
            }

            // Actualizar la cantidad real al momento de aplicar (pudo cambiar)
            $movement->quantity = -$quantity;
            $movement->save();

            event(new StockMovementCreated(
                movementId: $movement->id,
                productVariantId: $movement->product_variant_id,
                warehouseId: $movement->warehouse_id,
                movementType: 'expiration_write_off',
                quantity: $quantity,
            ));
        });
    }
}
