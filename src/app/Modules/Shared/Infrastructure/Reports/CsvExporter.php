<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Reports;

use App\Modules\Shared\Application\Services\ReportDataCollector;

class CsvExporter
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

        $filename = sprintf('%s_%s.csv', $reportType, now()->format('Ymd_His'));
        $path     = "{$directory}/{$filename}";

        $handle = fopen($path, 'w');
        fputcsv($handle, $data['headers'] ?? []);

        foreach ($data['rows'] ?? [] as $row) {
            fputcsv($handle, array_values($row));
        }

        fclose($handle);

        return $path;
    }
}
