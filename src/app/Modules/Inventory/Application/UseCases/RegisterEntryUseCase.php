<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Application\Services\MovementTimestamp;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Services\PresentationConverter;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\Inventory\Domain\Events\StockMovementCreated;
use App\Modules\Inventory\Domain\Exceptions\ProductNotFoundException;
use App\Modules\Inventory\Domain\Services\BatchLocationService;
use App\Modules\Inventory\Domain\Services\DocumentNumberGenerator;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RegisterEntryUseCase
{
    public function __construct(
        private readonly PresentationConverter $presentationConverter,
        private readonly DocumentNumberGenerator $numberGenerator,
        private readonly BatchLocationService $batchLocationService,
    ) {}

    public function execute(array $data): MovementDocumentModel
    {
        $movementDate = MovementTimestamp::resolve($data['movement_date'] ?? null);

        return DB::transaction(function () use ($data, $movementDate) {
            $document = MovementDocumentModel::create([
                'document_number'    => $this->numberGenerator->next('entry'),
                'document_type'      => 'entry',
                'warehouse_id'       => $data['warehouse_id'],
                'movement_date'      => $movementDate,
                'invoice_number'     => $data['invoice_number'] ?? null,
                'entry_temperature'  => isset($data['entry_temperature']) ? (float) $data['entry_temperature'] : null,
                'reason'             => $data['reason'] ?? null,
                'user_id'            => $data['user_id'],
                'status'             => 'confirmed',
            ]);

            foreach ($data['items'] as $item) {
                $this->processItem($item, $data['warehouse_id'], $data['user_id'], $document, $movementDate);
            }

            return $document->load(['movements.variant.genericProduct', 'movements.batch', 'warehouse', 'user']);
        });
    }

    private function processItem(array $item, int $warehouseId, int $userId, MovementDocumentModel $document, mixed $movementDate): void
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

        $this->batchLocationService->assertLocationInWarehouse((int) $item['location_id'], $warehouseId);

        // Los datos del lote se conservan tal como se registran. Si la fecha de
        // vencimiento ya pasó, el lote queda en estado `expired` al sincronizarse
        // y no estará disponible para salidas ni traslados.
        $batch = BatchModel::firstOrCreate(
            [
                'product_variant_id' => $item['product_variant_id'],
                'lot_number'         => $item['lot_number'],
            ],
            [
                'expiration_date'    => $item['expiration_date'],
                'manufacturing_date' => $item['manufacturing_date'] ?? null,
                'quantity_received'  => 0,
                'quantity_available' => 0,
                'status'             => 'active',
                'notes'              => $item['notes'] ?? null,
                'received_at'        => now(),
            ],
        );

        if (! $batch->wasRecentlyCreated) {
            $this->reconcileExistingBatchDates($batch, $item);
        }

        // Un reingreso del mismo lote conserva el comentario original; solo se
        // completa si el lote aún no tenía uno.
        if (! $batch->wasRecentlyCreated && $batch->notes === null && ! empty($item['notes'])) {
            $batch->update(['notes' => $item['notes']]);
        }

        $batch->increment('quantity_received', $quantity);

        $this->batchLocationService->deposit($batch->id, $warehouseId, (int) $item['location_id'], $quantity);

        $movement = StockMovementModel::create([
            'movement_document_id' => $document->id,
            'warehouse_id'         => $warehouseId,
            'product_variant_id'   => $item['product_variant_id'],
            'batch_id'             => $batch->id,
            'location_to_id'       => $item['location_id'],
            'movement_type'        => 'entry',
            'quantity'             => $quantity,
            'reason'               => $item['notes'] ?? null,
            'movement_date'        => $movementDate,
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

    /**
     * Un reingreso de un lote existente no puede cambiar en silencio su fecha
     * de vencimiento (antes `firstOrCreate` ignoraba la fecha nueva y el lote
     * conservaba la anterior, aunque fuera errónea).
     *
     * - Si la fecha guardada es corrupta (año fuera de 2000–2099, p. ej. 0028
     *   por un año de 2 dígitos), se corrige con la fecha recibida, ya validada.
     * - Si es una fecha válida distinta, se rechaza la entrada.
     */
    private function reconcileExistingBatchDates(BatchModel $batch, array $item): void
    {
        $incoming = Carbon::parse($item['expiration_date'])->startOfDay();
        $stored   = $batch->expiration_date?->copy()->startOfDay();

        if ($stored !== null && $stored->equalTo($incoming)) {
            return;
        }

        if ($stored === null || $this->isCorruptDate($stored)) {
            $updates = ['expiration_date' => $incoming->toDateString()];

            if (! empty($item['manufacturing_date'])
                && ($batch->manufacturing_date === null || $this->isCorruptDate($batch->manufacturing_date))) {
                $updates['manufacturing_date'] = Carbon::parse($item['manufacturing_date'])->toDateString();
            }

            $batch->update($updates);

            return;
        }

        throw new \DomainException(sprintf(
            'El lote %s ya está registrado con vencimiento %s y la fecha indicada (%s) no coincide. '
            . 'Verifique el número de lote o la fecha de vencimiento.',
            $batch->lot_number,
            $stored->format('d/m/Y'),
            $incoming->format('d/m/Y'),
        ));
    }

    private function isCorruptDate(\DateTimeInterface $date): bool
    {
        $year = (int) $date->format('Y');

        return $year < 2000 || $year > 2099;
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
