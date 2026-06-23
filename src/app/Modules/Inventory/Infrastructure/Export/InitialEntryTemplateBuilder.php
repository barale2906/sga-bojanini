<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Export;

use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Shared\Infrastructure\Export\ExcelTemplateWriter;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\WarehouseModel;
use App\Modules\Warehouse\Infrastructure\Persistence\Models\ZoneModel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Construye la plantilla Excel para la carga masiva de entradas de
 * inventario inicial.
 *
 * El almacén destino puede elegirse al importar (parámetro `warehouse_id`
 * del endpoint); si no se indica ninguno, se usa el primer almacén activo
 * del sistema. La zona y el estante dentro de ese almacén siempre se
 * resuelven automáticamente — ver
 * `App\Modules\Inventory\Application\Services\StorageAllocationService` —,
 * por lo que la plantilla solo pide el producto, el lote, la cantidad y las
 * fechas del lote.
 */
class InitialEntryTemplateBuilder
{
    public function __construct(
        private readonly ExcelTemplateWriter $writer,
    ) {}

    /**
     * @param  int|null  $warehouseId  solo afecta la hoja informativa "Almacén y zona destino";
     *                                 si se omite, esa hoja describe el almacén por defecto
     */
    public function build(?int $warehouseId = null): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        $this->writer->writeDataSheet(
            $spreadsheet->getActiveSheet(),
            'Entradas',
            [
                'product_code',
                'lot_number',
                'quantity',
                'expiration_date',
                'manufacturing_date',
                'notes',
            ],
            [
                'Código de producto',
                'Número de lote',
                'Cantidad (unidad base)',
                'Fecha de vencimiento',
                'Fecha de fabricación',
                'Notas',
            ],
            [
                'AGU-EJ-001',
                'LOTE-2024-001',
                '100',
                '2026-12-31',
                '2026-01-15',
                'Inventario inicial',
            ],
        );

        $this->writer->addInstructionsSheet($spreadsheet, [
            ['Columna', 'Nombre en español', 'Obligatorio', 'Tipo / formato', 'Descripción y valores válidos'],
            ['product_code', 'Código de producto', 'Sí', 'Texto', 'Código de un producto existente. Ver hoja "Productos".'],
            ['lot_number', 'Número de lote', 'Sí', 'Texto (máx. 100)', 'Número de lote del producto. Si ya existe un lote con este número para el mismo producto, se suma la cantidad a dicho lote en vez de crear uno nuevo.'],
            ['quantity', 'Cantidad', 'Sí', 'Entero >= 1', 'Cantidad en unidad base del producto. No se admiten presentaciones de compra en esta carga.'],
            ['expiration_date', 'Fecha de vencimiento', 'Sí', 'Fecha (AAAA-MM-DD)', 'Fecha de vencimiento del lote.'],
            ['manufacturing_date', 'Fecha de fabricación', 'No', 'Fecha (AAAA-MM-DD)', 'Fecha de fabricación del lote.'],
            ['notes', 'Notas', 'No', 'Texto', 'Observaciones de la entrada.'],
        ], [
            'La primera fila debe conservar exactamente estos nombres de columna (en minúsculas, sin tildes ni espacios).',
            'La fila 2 de la hoja "Entradas" (en español, gris) es solo de referencia: bórrela antes de cargar el archivo. Si no la borra, se reportará como 1 fila fallida, sin afectar a las demás entradas.',
            'Las filas completamente vacías se ignoran.',
            'Esta carga registra entradas reales de inventario (tipo "entry"), igual que el endpoint POST /movements/entry — no es un borrador ni requiere aprobación posterior.',
            'El almacén destino se indica al importar con el parámetro "warehouse_id" (ver hoja "Almacenes" para conocer los IDs válidos); si no se indica ninguno, se usa el primer almacén activo del sistema. Dentro del almacén elegido, la zona se asigna siempre automáticamente: su primera zona activa. Ver hoja "Almacén y zona destino" para ver el almacén/zona que aplicarían en este momento.',
            'Si el producto tiene marcado "Requiere cadena de frío" (ver hoja "Productos"), la entrada se dirige a la zona de tipo "cold" del almacén; si el almacén no tiene ninguna, el sistema la crea automáticamente con el nombre "Zona Refrigerada".',
            'Cada estante de destino tiene capacidad de 3 m³ y 500 kg. Si no hay estantes con espacio disponible en la zona, el sistema crea uno nuevo con esa misma capacidad. Si la cantidad de una sola fila excede la capacidad de un estante completo, esa fila se reporta como fallida y debe dividirse en varias filas con cantidades menores.',
            'No se admiten productos de tipo kit ni conversión de presentaciones de compra: la cantidad siempre se interpreta en la unidad base del producto.',
            'Esta importación solo crea entradas; no actualiza ni elimina lotes o movimientos existentes.',
        ]);

        $this->writer->addReferenceSheet(
            $spreadsheet,
            'Productos',
            ['code', 'name', 'requires_cold_chain', 'base_unit'],
            ProductModel::where('is_active', true)
                ->where('product_type', 'simple')
                ->with('baseUnit')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'requires_cold_chain', 'base_unit_id'])
                ->map(fn ($p) => [
                    $p->code,
                    $p->name,
                    $p->requires_cold_chain ? 'TRUE' : 'FALSE',
                    $p->baseUnit?->abbreviation ?? '',
                ])
                ->all(),
        );

        $this->writer->addReferenceSheet(
            $spreadsheet,
            'Almacenes',
            ['id', 'code', 'name'],
            WarehouseModel::where('is_active', true)
                ->orderBy('id')
                ->get(['id', 'code', 'name'])
                ->map(fn ($w) => [$w->id, $w->code, $w->name])
                ->all(),
        );

        $this->writer->addReferenceSheet(
            $spreadsheet,
            'Almacén y zona destino',
            ['warehouse', 'zone', 'zone_type'],
            $this->describeDestination($warehouseId),
        );

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /** @return array<int, array<int, string>> */
    private function describeDestination(?int $warehouseId): array
    {
        $query = WarehouseModel::where('is_active', true);

        $warehouse = $warehouseId !== null
            ? $query->where('id', $warehouseId)->first()
            : $query->orderBy('id')->first();

        if ($warehouse === null) {
            return [['(sin almacenes registrados)', '-', '-']];
        }

        $zone = ZoneModel::where('warehouse_id', $warehouse->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        return [[
            $warehouse->name,
            $zone?->name ?? '(sin zonas registradas)',
            $zone?->type ?? '-',
        ]];
    }
}
