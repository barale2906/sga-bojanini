<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Reports;

use App\Modules\Shared\Application\DTOs\GeneratedReportFile;
use App\Modules\Shared\Application\Services\ReportDataCollector;

class CsvExporter
{
    public function __construct(
        private readonly ReportDataCollector $dataCollector,
    ) {}

    public function export(string $reportType, array $filters): GeneratedReportFile
    {
        $data = $this->dataCollector->collect($reportType, $filters);

        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, $data['headers'] ?? []);

        foreach ($data['rows'] ?? [] as $row) {
            fputcsv($handle, array_values($row));
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return GeneratedReportFile::make($reportType, 'csv', 'text/csv', $content);
    }
}
