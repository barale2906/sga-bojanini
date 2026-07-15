<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Infrastructure\Persistence\Models\GenericProductModel;
use App\Modules\Inventory\Application\Services\StorageAllocationService;
use Illuminate\Support\Facades\Validator;

/**
 * Importa en bloque entradas de inventario inicial desde un Excel.
 *
 * Cada fila identifica el producto por `product_barcode` (código interno
 * del genérico). La variante se resuelve automáticamente tomando la primera
 * variante activa del genérico; para imports con múltiples variantes del
 * mismo genérico se debe usar la variante_id directamente.
 */
class ImportInitialEntriesUseCase
{
    public function __construct(
        private readonly StorageAllocationService $allocationService,
        private readonly RegisterEntryUseCase $registerEntryUseCase,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  int  $warehouseId
     * @param  int  $userId
     * @return array{total: int, success: int, failed: int, errors: array}
     */
    public function execute(array $rows, int $warehouseId, int $userId): array
    {
        $results = [
            'total'   => count($rows),
            'success' => 0,
            'failed'  => 0,
            'errors'  => [],
        ];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            try {
                $this->importRow($row, $warehouseId, $userId);
                $results['success']++;
            } catch (\Throwable $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'row'    => $rowNumber,
                    'errors' => $e instanceof \Illuminate\Validation\ValidationException
                        ? $e->errors()
                        : ['exception' => [$e->getMessage()]],
                ];
            }
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $row
     * @throws \Illuminate\Validation\ValidationException
     * @throws \DomainException
     */
    private function importRow(array $row, int $warehouseId, int $userId): void
    {
        $validated = Validator::validate($row, [
            'product_barcode'    => 'required|string|exists:product_generics,barcode',
            'lot_number'         => 'required|string|max:100',
            'quantity'           => 'required|integer|min:1',
            'expiration_date'    => 'required|date',
            'manufacturing_date' => 'nullable|date',
            'invoice_number'     => 'nullable|string|max:100',
            'entry_temperature'  => 'nullable|numeric',
            'notes'              => 'nullable|string',
        ], [
            'product_barcode.required' => 'El código de barras del producto es obligatorio.',
            'product_barcode.exists'   => 'No existe ningún producto con este código de barras. Revise la hoja "Productos" de la plantilla.',
            'lot_number.required'      => 'El número de lote es obligatorio.',
            'quantity.required'        => 'La cantidad es obligatoria.',
            'quantity.min'             => 'La cantidad debe ser mayor a cero.',
            'expiration_date.required' => 'La fecha de vencimiento es obligatoria.',
        ]);

        $generic = GenericProductModel::where('barcode', $validated['product_barcode'])
            ->with('variants')
            ->first();

        if ($generic->product_type === ProductType::Kit->value) {
            throw new \DomainException('No se pueden registrar entradas directas de productos tipo kit.');
        }

        $variant = $generic->variants()->where('is_active', true)->first();

        if ($variant === null) {
            throw new \DomainException("El producto {$validated['product_barcode']} no tiene variantes activas.");
        }

        $quantity = (int) $validated['quantity'];

        $zone = $this->allocationService->resolveTargetZone($warehouseId, (bool) $generic->requires_cold_chain);

        $location = $this->allocationService->allocateLocation(
            $zone->id,
            $quantity * (float) ($generic->volume_cm3 ?? 0),
            $quantity * (float) ($generic->weight_kg ?? 0),
        );

        $this->registerEntryUseCase->execute([
            'warehouse_id'      => $warehouseId,
            'invoice_number'    => $validated['invoice_number'] ?? null,
            'entry_temperature' => isset($validated['entry_temperature']) && $validated['entry_temperature'] !== '' ? (float) $validated['entry_temperature'] : null,
            'user_id'           => $userId,
            'items'             => [[
                'product_variant_id' => $variant->id,
                'location_id'        => $location->id,
                'lot_number'         => $validated['lot_number'],
                'expiration_date'    => $validated['expiration_date'],
                'manufacturing_date' => $validated['manufacturing_date'] ?? null,
                'quantity_base'      => $quantity,
                'notes'              => $validated['notes'] ?? null,
            ]],
        ]);
    }
}
