<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Reports;

use App\Modules\Shared\Application\Services\ReportDataCollector;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfExporter
{
    public function __construct(
        private readonly ReportDataCollector $dataCollector,
    ) {}

    public function export(string $reportType, array $filters): string
    {
        $data = $this->dataCollector->collect($reportType, $filters);
        $view = view()->exists("reports.{$reportType}")
            ? "reports.{$reportType}"
            : 'reports.generic';

        $pdf = Pdf::loadView($view, array_merge($data, [
            'title' => ucfirst($reportType),
        ]));

        $directory = storage_path('app/exports');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = sprintf('%s_%s.pdf', $reportType, now()->format('Ymd_His'));
        $path     = "{$directory}/{$filename}";
        $pdf->save($path);

        return $path;
    }
}
