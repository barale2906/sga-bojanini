<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Export;

use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductClassificationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Construye los archivos Excel de plantilla para la importación masiva
 * de productos y proveedores, incluyendo hojas de instrucciones y
 * catálogos de referencia (categorías, unidades, clasificaciones).
 */
class ImportTemplateBuilder
{
    public const ENTITIES = ['products', 'suppliers'];

    public function build(string $entity): Spreadsheet
    {
        return match ($entity) {
            'products'  => $this->buildProducts(),
            'suppliers' => $this->buildSuppliers(),
            default     => throw new InvalidArgumentException("Plantilla no soportada: {$entity}"),
        };
    }

    private function buildProducts(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        $this->writeDataSheet(
            $spreadsheet->getActiveSheet(),
            'Productos',
            [
                'name',
                'code',
                'sku',
                'category_code',
                'unit_abbreviation',
                'classification_code',
                'description',
                'requires_cold_chain',
                'reorder_point',
                'reorder_quantity',
                'min_stock',
                'max_stock',
            ],
            [
                'Aguja 21G ejemplo',
                'AGU-EJ-001',
                'SKU-001',
                'INS-MED',
                'UND',
                'DM',
                'Aguja hipodérmica 21G',
                'FALSE',
                '100',
                '500',
                '50',
                '10000',
            ],
        );

        $this->addInstructionsSheet($spreadsheet, [
            ['Columna', 'Obligatorio', 'Tipo / formato', 'Descripción y valores válidos'],
            ['name', 'Sí', 'Texto (máx. 255)', 'Nombre del producto.'],
            ['code', 'Sí', 'Texto (máx. 50)', 'Código único del producto. No debe repetirse con productos existentes ni con otras filas del archivo.'],
            ['sku', 'No', 'Texto (máx. 100)', 'Código SKU. Si se diligencia, debe ser único.'],
            ['category_code', 'Sí', 'Texto', 'Código de categoría existente. Ver hoja "Categorías".'],
            ['unit_abbreviation', 'Sí', 'Texto', 'Abreviatura de la unidad de medida base existente. Ver hoja "Unidades de medida".'],
            ['classification_code', 'No', 'Texto', 'Código de clasificación de producto existente. Ver hoja "Clasificaciones". Si se deja vacío, el producto queda sin clasificación.'],
            ['description', 'No', 'Texto', 'Descripción del producto.'],
            ['requires_cold_chain', 'No', 'Booleano', 'Valores aceptados: TRUE/FALSE, 1/0, yes/no. Si se deja vacío se asume FALSE.'],
            ['reorder_point', 'No', 'Entero >= 0', 'Punto de reorden. Si se deja vacío se asume 0.'],
            ['reorder_quantity', 'No', 'Entero >= 0', 'Cantidad de reorden. Si se deja vacío se asume 0.'],
            ['min_stock', 'No', 'Entero >= 0', 'Stock mínimo. Si se deja vacío se asume 0.'],
            ['max_stock', 'No', 'Entero >= 0', 'Stock máximo. Si se deja vacío se asume 0.'],
        ], [
            'La primera fila debe conservar exactamente estos nombres de columna (en minúsculas, sin tildes ni espacios).',
            'Las filas completamente vacías se ignoran.',
            'Todos los productos importados se crean como tipo "simple" (no se admite la importación de kits).',
            'Todos los productos importados quedan activos (is_active = true).',
            'No se importan presentaciones, proveedores, registros sanitarios ni campos avanzados (concentración, nivel de riesgo, laboratorio/marca, forma farmacéutica, presentación comercial, referencia de serie, vida útil, volumen, peso). Estos datos se deben completar luego editando cada producto.',
            'Esta importación solo crea productos nuevos; no actualiza productos existentes.',
            'Las hojas "Categorías", "Unidades de medida" y "Clasificaciones" muestran los códigos vigentes Y permiten crear nuevos: agregue filas nuevas al final con los datos requeridos y se crearán automáticamente antes de procesar la hoja "Productos" (así puede usarlas el mismo archivo en category_code/unit_abbreviation/classification_code). Las filas cuyo código ya existe se omiten sin generar error.',
        ]);

        $this->addReferenceSheet(
            $spreadsheet,
            'Categorías',
            ['code', 'name', 'parent_code', 'description'],
            CategoryModel::where('is_active', true)
                ->with('parent')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'description', 'parent_id'])
                ->map(fn ($c) => [$c->code, $c->name, $c->parent?->code ?? '', $c->description ?? ''])
                ->all(),
        );

        $this->addReferenceSheet(
            $spreadsheet,
            'Unidades de medida',
            ['abbreviation', 'name', 'is_base'],
            UnitOfMeasureModel::where('is_active', true)
                ->orderBy('abbreviation')
                ->get(['abbreviation', 'name', 'is_base'])
                ->map(fn ($u) => [$u->abbreviation, $u->name, $u->is_base ? 'TRUE' : 'FALSE'])
                ->all(),
        );

        $this->addReferenceSheet(
            $spreadsheet,
            'Clasificaciones',
            [
                'code', 'name', 'description',
                'has_sanitary_registration', 'has_concentration', 'has_risk_level',
                'has_pharma_fields', 'has_device_fields', 'has_lab_brand',
            ],
            ProductClassificationModel::where('is_active', true)
                ->orderBy('code')
                ->get([
                    'code', 'name', 'description',
                    'has_sanitary_registration', 'has_concentration', 'has_risk_level',
                    'has_pharma_fields', 'has_device_fields', 'has_lab_brand',
                ])
                ->map(fn ($c) => [
                    $c->code, $c->name, $c->description ?? '',
                    $c->has_sanitary_registration ? 'TRUE' : 'FALSE',
                    $c->has_concentration ? 'TRUE' : 'FALSE',
                    $c->has_risk_level ? 'TRUE' : 'FALSE',
                    $c->has_pharma_fields ? 'TRUE' : 'FALSE',
                    $c->has_device_fields ? 'TRUE' : 'FALSE',
                    $c->has_lab_brand ? 'TRUE' : 'FALSE',
                ])
                ->all(),
        );

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function buildSuppliers(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        $this->writeDataSheet(
            $spreadsheet->getActiveSheet(),
            'Proveedores',
            [
                'name',
                'tax_id',
                'contact_name',
                'phone',
                'email',
                'address',
                'notes',
            ],
            [
                'Proveedor Ejemplo S.A.S.',
                '900123456-1',
                'Juan Pérez',
                '3001234567',
                'contacto@proveedor.com',
                'Calle 100 #10-20',
                'Proveedor de insumos médicos',
            ],
        );

        $this->addInstructionsSheet($spreadsheet, [
            ['Columna', 'Obligatorio', 'Tipo / formato', 'Descripción y valores válidos'],
            ['name', 'Sí', 'Texto (máx. 255)', 'Nombre o razón social del proveedor.'],
            ['tax_id', 'No', 'Texto (máx. 50)', 'NIT o identificación tributaria. Si se diligencia, debe ser único.'],
            ['contact_name', 'No', 'Texto (máx. 255)', 'Nombre de la persona de contacto.'],
            ['phone', 'No', 'Texto (máx. 50)', 'Teléfono de contacto.'],
            ['email', 'No', 'Email (máx. 255)', 'Correo electrónico de contacto. Debe tener formato de correo válido.'],
            ['address', 'No', 'Texto', 'Dirección.'],
            ['notes', 'No', 'Texto', 'Notas adicionales.'],
        ], [
            'La primera fila debe conservar exactamente estos nombres de columna (en minúsculas, sin tildes ni espacios).',
            'Las filas completamente vacías se ignoran.',
            'Todos los proveedores importados quedan activos (is_active = true).',
            'Esta importación solo crea proveedores nuevos; no actualiza proveedores existentes.',
        ]);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * @param  string[]  $headers
     * @param  string[]  $exampleRow
     */
    private function writeDataSheet(Worksheet $sheet, string $title, array $headers, array $exampleRow): void
    {
        $sheet->setTitle($title);

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
            $sheet->setCellValueByColumnAndRow($col + 1, 2, $exampleRow[$col] ?? '');
        }

        $lastColumn = $sheet->getCellByColumnAndRow(count($headers), 1)->getColumn();
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E1F2');

        foreach (range('A', $lastColumn) as $columnId) {
            $sheet->getColumnDimension($columnId)->setAutoSize(true);
        }

        $sheet->freezePane('A2');
    }

    /**
     * @param  array<int, array<int, string>>  $rows  primera fila es el encabezado
     * @param  string[]  $notes
     */
    private function addInstructionsSheet(Spreadsheet $spreadsheet, array $rows, array $notes): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Instrucciones');

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 1, $value);
            }
        }

        $lastColumn = $sheet->getCellByColumnAndRow(count($rows[0]), 1)->getColumn();
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E1F2');

        foreach (range('A', $lastColumn) as $columnId) {
            $sheet->getColumnDimension($columnId)->setAutoSize(true);
        }

        $notesStartRow = count($rows) + 2;
        $sheet->setCellValueByColumnAndRow(1, $notesStartRow, 'Notas generales');
        $sheet->getStyle("A{$notesStartRow}")->getFont()->setBold(true);

        foreach ($notes as $index => $note) {
            $sheet->setCellValueByColumnAndRow(1, $notesStartRow + 1 + $index, '- '.$note);
        }
    }

    /**
     * @param  string[]  $headers
     * @param  array<int, array<int, string|null>>  $rows
     */
    private function addReferenceSheet(Spreadsheet $spreadsheet, string $title, array $headers, array $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $value) {
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $rowIndex + 2, $value);
            }
        }

        $lastColumn = $sheet->getCellByColumnAndRow(count($headers), 1)->getColumn();
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E1F2');

        foreach (range('A', $lastColumn) as $columnId) {
            $sheet->getColumnDimension($columnId)->setAutoSize(true);
        }

        $sheet->freezePane('A2');
    }
}
