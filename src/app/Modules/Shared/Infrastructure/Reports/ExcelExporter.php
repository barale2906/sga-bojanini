<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Reports;

use App\Modules\Shared\Application\Services\ReportDataCollector;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ExcelExporter
{
    public function __construct(
        private readonly ReportDataCollector $dataCollector,
    ) {}

    public function export(string $reportType, array $filters): string
    {
        $data = $this->dataCollector->collect($reportType, $filters);

        $directory = storage_path('app/exports');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = sprintf('%s_%s.xlsx', $reportType, now()->format('Ymd_His'));
        $path     = "{$directory}/{$filename}";

        $rows = array_map(
            fn (array $row) => array_values($row),
            $data['rows'] ?? [],
        );

        $export = new class ($data['headers'] ?? [], $rows) implements FromArray, WithHeadings {
            public function __construct(
                private readonly array $headings,
                private readonly array $rows,
            ) {}

            public function headings(): array
            {
                return $this->headings;
            }

            public function array(): array
            {
                return $this->rows;
            }
        };

        Excel::store($export, "exports/{$filename}", 'local');

        return storage_path("app/exports/{$filename}");
    }
}
