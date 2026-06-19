<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Reports;

use App\Modules\Shared\Application\DTOs\GeneratedReportFile;
use App\Modules\Shared\Application\Services\ReportDataCollector;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfExporter
{
    public function __construct(
        private readonly ReportDataCollector $dataCollector,
    ) {}

    public function export(string $reportType, array $filters): GeneratedReportFile
    {
        $data = $this->dataCollector->collect($reportType, $filters);
        $view = view()->exists("reports.{$reportType}")
            ? "reports.{$reportType}"
            : 'reports.generic';

        $pdf = Pdf::loadView($view, array_merge($data, [
            'title' => ucfirst($reportType),
        ]));

        return GeneratedReportFile::make($reportType, 'pdf', 'application/pdf', $pdf->output());
    }
}
