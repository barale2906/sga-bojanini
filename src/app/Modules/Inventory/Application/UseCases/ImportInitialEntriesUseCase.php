<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Inventory\Application\Services\StorageAllocationService;
use Illuminate\Support\Facades\Validator;

/**
 * Importa en bloque entradas de inventario inicial desde un Excel.
 *
 * Por cada fila resuelve el producto por código, calcula su destino
 * (almacén → zona → estante) mediante `StorageAllocationService` y delega
 * el registro a `RegisterEntryUseCase` — el mismo caso de uso que usa el
 * endpoint manual `POST /movements/entry` —, de forma que una entrada
 * importada es indistinguible de una registrada a mano.
 *
 * Sigue el mismo contrato de resultado que los demás importadores del
 * sistema (ver `Catalog\Domain\Services\ProductImportService`):
 * total/success/failed/errors, continuando con la siguiente fila si una
 * falla.
 */
class ImportInitialEntriesUseCase
{
    public function __construct(
        private readonly StorageAllocationService $allocationService,
        private readonly RegisterEntryUseCase $registerEntryUseCase,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows  filas crudas leídas de la hoja "Entradas" del Excel
     * @param  int  $warehouseId  almacén destino ya resuelto (elegido por el usuario o el de `StorageAllocationService::resolveDefaultWarehouse()`)
     * @param  int  $userId  usuario que ejecuta la importación, queda registrado en cada `StockMovementModel`
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
     * Valida una fila, resuelve su producto/zona/estante y registra la
     * entrada. Lanza excepción (capturada por `execute()`) ante cualquier
     * fallo — no hay rollback parcial porque cada llamada a
     * `RegisterEntryUseCase::execute()` ya es transaccional por sí misma.
     *
     * @param  array<string, mixed>  $row
     * @throws \Illuminate\Validation\ValidationException si la fila no cumple las reglas de validación
     * @throws \DomainException si el producto es tipo kit o si `StorageAllocationService` no puede resolver el destino
     */
    private function importRow(array $row, int $warehouseId, int $userId): void
    {
        $validated = Validator::validate($row, [
            'product_code'       => 'required|string|exists:products,code',
            'lot_number'         => 'required|string|max:100',
            'quantity'           => 'required|integer|min:1',
            'expiration_date'    => 'required|date',
            'manufacturing_date' => 'nullable|date',
            'notes'              => 'nullable|string',
        ], [
            'product_code.required'    => 'El código de producto es obligatorio.',
            'product_code.exists'      => 'No existe ningún producto con este código. Revise la hoja "Productos" de la plantilla.',
            'lot_number.required'      => 'El número de lote es obligatorio.',
            'quantity.required'        => 'La cantidad es obligatoria.',
            'quantity.min'              => 'La cantidad debe ser mayor a cero.',
            'expiration_date.required' => 'La fecha de vencimiento es obligatoria.',
        ]);

        $product = ProductModel::where('code', $validated['product_code'])->first();

        if ($product->product_type === ProductType::Kit->value) {
            throw new \DomainException('No se pueden registrar entradas directas de productos tipo kit.');
        }

        $quantity = (int) $validated['quantity'];

        $zone = $this->allocationService->resolveTargetZone($warehouseId, (bool) $product->requires_cold_chain);

        $location = $this->allocationService->allocateLocation(
            $zone->id,
            $quantity * (float) ($product->volume_cm3 ?? 0),
            $quantity * (float) ($product->weight_kg ?? 0),
        );

        $this->registerEntryUseCase->execute([
            'product_id'         => $product->id,
            'warehouse_id'       => $warehouseId,
            'location_id'        => $location->id,
            'lot_number'         => $validated['lot_number'],
            'expiration_date'    => $validated['expiration_date'],
            'manufacturing_date' => $validated['manufacturing_date'] ?? null,
            'quantity_base'      => $quantity,
            'notes'              => $validated['notes'] ?? null,
            'user_id'            => $userId,
        ]);
    }
}
