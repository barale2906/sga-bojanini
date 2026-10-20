<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Import;

use App\Modules\Shared\Infrastructure\Import\HeadingRowSheetImport;
use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Lector del archivo de importación de activos (ver AssetImportTemplateBuilder).
 * Las hojas distintas a "Activos" son opcionales.
 */
class AssetsImport implements SkipsUnknownSheets, WithMultipleSheets
{
    public const SHEET_ASSETS     = 'Activos';
    public const SHEET_BIOMEDICAL = 'Hoja de vida biomédica';
    public const SHEET_COMPUTING  = 'Hoja de vida cómputo';
    public const SHEET_CATEGORIES = 'Categorías';
    public const SHEET_PLACES     = 'Sedes y sitios';

    /** @var array<string, HeadingRowSheetImport> */
    private array $sheets;

    public function __construct()
    {
        $this->sheets = [
            self::SHEET_ASSETS     => new HeadingRowSheetImport(['serial', 'category_code', 'supplier_tax_id', 'invoice_number', 'site_name', 'place_name']),
            self::SHEET_BIOMEDICAL => new HeadingRowSheetImport(['serial', 'invima_registry', 'voltage', 'current', 'power', 'frequency']),
            self::SHEET_COMPUTING  => new HeadingRowSheetImport(['serial', 'ram', 'storage', 'ip_address', 'mac_address', 'hostname']),
            self::SHEET_CATEGORIES => new HeadingRowSheetImport(['code']),
            self::SHEET_PLACES     => new HeadingRowSheetImport(['site_name', 'place_name']),
        ];
    }

    public function sheets(): array
    {
        return $this->sheets;
    }

    public function onUnknownSheet($sheetName): void
    {
        // Hojas opcionales o de ayuda (Instrucciones): se ignoran.
    }

    /** @return array<string, array<int, array<string, mixed>>> filas por nombre de hoja */
    public function rowsBySheet(): array
    {
        return array_map(fn (HeadingRowSheetImport $sheet) => $sheet->getRows(), $this->sheets);
    }
}
