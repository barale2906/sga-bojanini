<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

use App\Modules\Shared\Infrastructure\Reports\CsvExporter;
use App\Modules\Shared\Infrastructure\Reports\ExcelExporter;
use App\Modules\Shared\Infrastructure\Reports\PdfExporter;

class ReportExportService
{
    public function __construct(
        private readonly PdfExporter $pdfExporter,
        private readonly ExcelExporter $excelExporter,
        private readonly CsvExporter $csvExporter,
    ) {}

    public function generate(string $reportType, array $filters, string $format): string
    {
        return match ($format) {
            'pdf'   => $this->pdfExporter->export($reportType, $filters),
            'excel' => $this->excelExporter->export($reportType, $filters),
            'csv'   => $this->csvExporter->export($reportType, $filters),
            default => throw new \InvalidArgumentException("Formato no soportado: {$format}"),
        };
    }
}
