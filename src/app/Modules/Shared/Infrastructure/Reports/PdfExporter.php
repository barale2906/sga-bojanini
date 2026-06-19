<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Reports;

use App\Modules\Shared\Application\DTOs\GeneratedReportFile;
use App\Modules\Shared\Application\Services\ReportDataCollector;
use Barryvdh\DomPDF\Facade\Pdf;

class PdfExporter
{
    /** Títulos en español mostrados en el PDF, por tipo de reporte. */
    private const TITLES = [
        'inventory'   => 'Reporte de Inventario',
        'movements'   => 'Reporte de Movimientos',
        'expiring'    => 'Reporte de Lotes por Vencer',
        'purchases'   => 'Reporte de Compras',
        'consumption' => 'Reporte de Consumo',
        'conditions'  => 'Reporte de Condiciones de Almacenamiento',
        'audit'       => 'Reporte de Auditoría',
    ];

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
            'title' => self::TITLES[$reportType] ?? ucfirst($reportType),
        ]));

        return GeneratedReportFile::make($reportType, 'pdf', 'application/pdf', $pdf->output());
    }
}
