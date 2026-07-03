<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Export;

use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductClassificationModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
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
                'volume_cm3',
                'weight_kg',
                'concentration',
                'risk_level',
                'lab_brand',
                'pharmaceutical_form',
                'commercial_presentation',
                'serie_reference',
                'useful_life',
                'sanitary_registration',
            ],
            [
                'Nombre',
                'Código',
                'SKU',
                'Código de categoría',
                'Unidad de medida (abrev.)',
                'Código de clasificación',
                'Descripción',
                'Requiere cadena de frío',
                'Punto de reorden',
                'Cantidad de reorden',
                'Stock mínimo',
                'Stock máximo',
                'Volumen (cm³)',
                'Peso (kg)',
                'Concentración (medicamentos)',
                'Nivel de riesgo (dispositivos médicos)',
                'Laboratorio / Marca',
                'Forma farmacéutica (medicamentos)',
                'Presentación comercial (medicamentos)',
                'Serie / Referencia (dispositivos médicos)',
                'Vida útil (dispositivos médicos)',
                'Registro sanitario (INVIMA)',
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
                '1.50',
                '0.010',
                '',
                'Clase IIA',
                'Acme Medical',
                '',
                '',
                'LOTE-2024-001',
                '5 años',
                '2024DM-0012345',
            ],
            textColumns: [2, 3], // code, sku → siempre texto aunque sean numéricos
        );

        $this->addInstructionsSheet($spreadsheet, [
            ['Columna', 'Nombre en español', 'Obligatorio', 'Tipo / formato', 'Descripción y valores válidos'],
            ['name', 'Nombre', 'Sí', 'Texto (máx. 255)', 'Nombre del producto.'],
            ['code', 'Código', 'Sí', 'Texto (máx. 50)', 'Código único del producto. No debe repetirse con productos existentes ni con otras filas del archivo.'],
            ['sku', 'SKU', 'No', 'Texto (máx. 100)', 'Código SKU. Si se diligencia, debe ser único.'],
            ['category_code', 'Código de categoría', 'Sí', 'Texto', 'Código de categoría existente. Ver hoja "Categorías".'],
            ['unit_abbreviation', 'Unidad de medida (abrev.)', 'Sí', 'Texto', 'Abreviatura de la unidad de medida base existente. Ver hoja "Unidades de medida".'],
            ['classification_code', 'Código de clasificación', 'No', 'Texto', 'Código de clasificación de producto existente. Ver hoja "Clasificaciones". Si se deja vacío, el producto queda sin clasificación.'],
            ['description', 'Descripción', 'No', 'Texto', 'Descripción del producto.'],
            ['requires_cold_chain', 'Requiere cadena de frío', 'No', 'Booleano', 'Valores aceptados: TRUE/FALSE, 1/0, yes/no. Si se deja vacío se asume FALSE.'],
            ['reorder_point', 'Punto de reorden', 'No', 'Entero >= 0', 'Punto de reorden. Si se deja vacío se asume 0.'],
            ['reorder_quantity', 'Cantidad de reorden', 'No', 'Entero >= 0', 'Cantidad de reorden. Si se deja vacío se asume 0.'],
            ['min_stock', 'Stock mínimo', 'No', 'Entero >= 0', 'Stock mínimo. Si se deja vacío se asume 0.'],
            ['max_stock', 'Stock máximo', 'No', 'Entero >= 0', 'Stock máximo. Si se deja vacío se asume 0.'],
            ['volume_cm3', 'Volumen (cm³)', 'No', 'Decimal >= 0', 'Volumen de una unidad del producto, en cm³. Use punto decimal (ej: 1.50).'],
            ['weight_kg', 'Peso (kg)', 'No', 'Decimal >= 0', 'Peso de una unidad del producto, en kg. Use punto decimal (ej: 0.010).'],
            ['concentration', 'Concentración', 'No', 'Texto (máx. 100)', 'Solo aplica a medicamentos (ej: 500mg/5ml). Aplica a clasificaciones con "has_concentration".'],
            ['risk_level', 'Nivel de riesgo', 'No', 'Texto (máx. 100)', 'Solo aplica a dispositivos médicos (ej: Clase IIA). Aplica a clasificaciones con "has_risk_level".'],
            ['lab_brand', 'Laboratorio / Marca', 'No', 'Texto (máx. 255)', 'Laboratorio fabricante o marca comercial. Aplica a clasificaciones con "has_lab_brand".'],
            ['pharmaceutical_form', 'Forma farmacéutica', 'No', 'Texto (máx. 150)', 'Solo aplica a medicamentos (ej: Tableta, Jarabe, Ampolla). Aplica a clasificaciones con "has_pharma_fields".'],
            ['commercial_presentation', 'Presentación comercial', 'No', 'Texto (máx. 150)', 'Solo aplica a medicamentos (ej: Frasco x120ml, Caja x10 Tab). Aplica a clasificaciones con "has_pharma_fields".'],
            ['serie_reference', 'Serie / Referencia', 'No', 'Texto (máx. 150)', 'Solo aplica a dispositivos médicos. Aplica a clasificaciones con "has_device_fields".'],
            ['useful_life', 'Vida útil', 'No', 'Texto (máx. 100)', 'Solo aplica a dispositivos médicos (ej: 5 años, Permanente). Aplica a clasificaciones con "has_device_fields".'],
            ['sanitary_registration', 'Registro sanitario (INVIMA)', 'No', 'Texto (máx. 100)', 'Número de registro sanitario INVIMA (ej: 2024DM-0012345). Si se diligencia, se crea el registro sanitario del producto con fecha de vencimiento 31/12/2099 (actualícela luego en el módulo de productos).'],
        ], [
            'La primera fila debe conservar exactamente estos nombres de columna (en minúsculas, sin tildes ni espacios).',
            'La fila 2 de la hoja "Productos" (en español, gris) es solo de referencia: bórrela antes de cargar el archivo. Si no la borra, se reportará como 1 fila fallida, sin afectar a los demás productos.',
            'Las filas completamente vacías se ignoran.',
            'Todos los productos importados se crean como tipo "simple" (no se admite la importación de kits).',
            'Todos los productos importados quedan activos (is_active = true).',
            'Los campos concentration/risk_level/lab_brand/pharmaceutical_form/commercial_presentation/serie_reference/useful_life son de texto libre: se guardan tal cual se diligencien, sin validar que correspondan a los indicadores has_* de la clasificación indicada en "classification_code". Revise la hoja "Clasificaciones" para saber qué campos aplican a cada clasificación.',
            'Si se diligencia "sanitary_registration", se crea automáticamente el registro sanitario del producto con fecha de vencimiento 31/12/2099. Puede actualizar esa fecha luego desde el módulo de productos.',
            'No se importan presentaciones ni proveedores asociados al producto. Estos datos se deben completar luego editando cada producto.',
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
                'Nombre / Razón social',
                'NIT / Identificación',
                'Nombre de contacto',
                'Teléfono',
                'Correo electrónico',
                'Dirección',
                'Notas',
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
            ['Columna', 'Nombre en español', 'Obligatorio', 'Tipo / formato', 'Descripción y valores válidos'],
            ['name', 'Nombre / Razón social', 'Sí', 'Texto (máx. 255)', 'Nombre o razón social del proveedor.'],
            ['tax_id', 'NIT / Identificación', 'No', 'Texto (máx. 50)', 'NIT o identificación tributaria. Si se diligencia, debe ser único.'],
            ['contact_name', 'Nombre de contacto', 'No', 'Texto (máx. 255)', 'Nombre de la persona de contacto.'],
            ['phone', 'Teléfono', 'No', 'Texto (máx. 50)', 'Teléfono de contacto.'],
            ['email', 'Correo electrónico', 'No', 'Email (máx. 255)', 'Correo electrónico de contacto. Debe tener formato de correo válido.'],
            ['address', 'Dirección', 'No', 'Texto', 'Dirección.'],
            ['notes', 'Notas', 'No', 'Texto', 'Notas adicionales.'],
        ], [
            'La primera fila debe conservar exactamente estos nombres de columna (en minúsculas, sin tildes ni espacios).',
            'La fila 2 de la hoja "Proveedores" (en español, gris) es solo de referencia: bórrela antes de cargar el archivo. Si no la borra, se reportará como 1 fila fallida, sin afectar a los demás proveedores.',
            'Las filas completamente vacías se ignoran.',
            'Todos los proveedores importados quedan activos (is_active = true).',
            'Esta importación solo crea proveedores nuevos; no actualiza proveedores existentes.',
        ]);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Escribe la hoja de datos con: fila 1 = encabezado técnico (el que lee el
     * importador, no se debe traducir), fila 2 = nombre de cada columna en
     * español (solo de referencia visual, se debe borrar antes de cargar el
     * archivo) y fila 3 = ejemplo de datos.
     *
     * @param  string[]  $headers
     * @param  string[]  $spanishLabels
     * @param  string[]  $exampleRow
     * @param  int[]     $textColumns  índices base-1 de columnas que deben formatearse como texto
     */
    private function writeDataSheet(Worksheet $sheet, string $title, array $headers, array $spanishLabels, array $exampleRow, array $textColumns = []): void
    {
        $sheet->setTitle($title);

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
            $sheet->setCellValueByColumnAndRow($col + 1, 2, $spanishLabels[$col] ?? '');
            $sheet->setCellValueByColumnAndRow($col + 1, 3, $exampleRow[$col] ?? '');
        }

        $lastColumn = $sheet->getCellByColumnAndRow(count($headers), 1)->getColumn();
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E1F2');

        $sheet->getStyle("A2:{$lastColumn}2")->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF7F7F7F'));
        $sheet->getStyle("A2:{$lastColumn}2")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F2F2F2');
        $sheet->getStyle("A2:{$lastColumn}2")->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(2)->setRowHeight(30);

        foreach (range('A', $lastColumn) as $columnId) {
            $sheet->getColumnDimension($columnId)->setAutoSize(true);
        }

        $sheet->getComment('A1')->getText()->createTextRun(
            'La fila 2 (gris, en español) es solo de referencia para saber qué va en cada columna. '
            .'BÓRRELA antes de cargar el archivo al sistema; si la deja, se reportará como 1 fila '
            .'fallida (no afecta a los demás registros). Ver hoja "Instrucciones" para el detalle completo.'
        );

        foreach ($textColumns as $colIndex) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            // Formato texto para las filas de datos (fila 4 en adelante hasta 5003).
            // No se usa toda la columna para no agotar memoria con PhpSpreadsheet.
            $sheet->getStyle("{$colLetter}4:{$colLetter}5003")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        $sheet->freezePane('A3');
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
