<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class GenerateImportTemplatesCommand extends Command
{
    protected $signature = 'sga:generate-import-templates';

    protected $description = 'Genera plantillas Excel para importación de productos y proveedores';

    public function handle(): int
    {
        $directory = storage_path('app/templates');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $this->writeSpreadsheet(
            "{$directory}/products_template.xlsx",
            [
                'name',
                'code',
                'sku',
                'category_code',
                'unit_abbreviation',
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
                'Aguja hipodérmica 21G',
                'false',
                '100',
                '500',
                '50',
                '10000',
            ],
        );

        $this->writeSpreadsheet(
            "{$directory}/suppliers_template.xlsx",
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

        $this->info("Plantillas generadas en {$directory}");

        return self::SUCCESS;
    }

  /**
     * @param  string[]  $headers
     * @param  string[]  $exampleRow
     */
    private function writeSpreadsheet(string $path, array $headers, array $exampleRow): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($headers as $col => $header) {
            $sheet->setCellValue([$col + 1, 1], $header);
            $sheet->setCellValue([$col + 1, 2], $exampleRow[$col] ?? '');
        }

        (new Xlsx($spreadsheet))->save($path);
    }
}
