<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Export;

use App\Modules\Assets\Application\Import\AssetImportLayout;
use App\Modules\Assets\Infrastructure\Import\AssetsImport;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetCategoryModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetPlaceModel;
use App\Modules\Shared\Infrastructure\Export\ExcelTemplateWriter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Plantilla Excel de importación de activos, generada en cada descarga con
 * los catálogos vigentes. Las hojas "Categorías" y "Sedes y sitios" son de
 * referencia y además crean los registros nuevos que se agreguen al final.
 */
class AssetImportTemplateBuilder
{
    private const HELP_COMMENT = 'La fila 2 (gris, en español) es solo de referencia. Si no la borra, el sistema la ignora. '
        . 'Ver hoja "Instrucciones" para el detalle completo.';

    public function __construct(
        private readonly ExcelTemplateWriter $writer,
    ) {}

    public function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        $this->dataSheet($spreadsheet->getActiveSheet(), AssetsImport::SHEET_ASSETS, AssetImportLayout::ASSET_COLUMNS);
        $this->dataSheet($spreadsheet->createSheet(), AssetsImport::SHEET_BIOMEDICAL, AssetImportLayout::biomedicalColumns());
        $this->dataSheet($spreadsheet->createSheet(), AssetsImport::SHEET_COMPUTING, AssetImportLayout::computingColumns());

        $this->writer->addReferenceSheet(
            $spreadsheet,
            AssetsImport::SHEET_CATEGORIES,
            ['code', 'name', 'asset_type', 'description'],
            AssetCategoryModel::where('is_active', true)->orderBy('code')->get()
                ->map(fn ($c) => [$c->code, $c->name, $c->asset_type->value, $c->description])
                ->all(),
        );

        $this->writer->addReferenceSheet(
            $spreadsheet,
            AssetsImport::SHEET_PLACES,
            ['site_name', 'place_name'],
            AssetPlaceModel::with('site')->where('is_active', true)->get()
                ->sortBy(fn ($p) => [$p->site?->name, $p->name])
                ->map(fn ($p) => [$p->site?->name, $p->name])
                ->values()
                ->all(),
        );

        $this->writer->addInstructionsSheet($spreadsheet, $this->instructionRows(), [
            'La fila 1 de cada hoja debe conservar exactamente los nombres de columna (en minúsculas, sin tildes).',
            'La fila 2 (en español) es solo de ayuda: si no se borra, se ignora.',
            'La fila 3 de las hojas de datos es un EJEMPLO: reemplácela con sus datos o bórrela; si la deja, se importará.',
            'El código de cada activo se genera automáticamente (000001, 000002, …); no se incluye en el archivo.',
            'Orden de proceso: 1) Categorías y Sedes y sitios (las filas nuevas se crean, las existentes se omiten); 2) Activos; 3) Hojas de vida.',
            'Si la sede o el sitio de un activo no existen, se crean automáticamente.',
            'Las hojas de vida se relacionan con el activo por su serial; las de cómputo solo aplican a categorías de tipo computing y las biomédicas a biomedical.',
            'Si se indica tipo de mantenimiento, se crea el plan periódico y se programa la próxima orden desde la fecha del último mantenimiento (o desde hoy).',
            'Las categorías de tipo general (mobiliario, TV, etc.) no admiten hoja de vida ni planes.',
            'Una fila con error no detiene la importación: el resumen indica hoja, fila y motivo.',
            'Valores válidos de asset_type en "Categorías": biomedical, computing, general.',
        ]);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function dataSheet(Worksheet $sheet, string $title, array $columns): void
    {
        $this->writer->writeDataSheet(
            $sheet,
            $title,
            AssetImportLayout::headers($columns),
            AssetImportLayout::labels($columns),
            AssetImportLayout::examples($columns),
            self::HELP_COMMENT,
        );
    }

    /** @return array<int, array<int, string>> */
    private function instructionRows(): array
    {
        $rows = [['Hoja', 'Columna', 'Obligatorio', 'Descripción y valores válidos']];

        foreach ([
            AssetsImport::SHEET_ASSETS     => AssetImportLayout::ASSET_COLUMNS,
            AssetsImport::SHEET_BIOMEDICAL => AssetImportLayout::biomedicalColumns(),
            AssetsImport::SHEET_COMPUTING  => AssetImportLayout::computingColumns(),
        ] as $sheet => $columns) {
            foreach ($columns as [$header, , , $required, $description]) {
                $rows[] = [$sheet, $header, $required, $description];
            }
        }

        return $rows;
    }
}
