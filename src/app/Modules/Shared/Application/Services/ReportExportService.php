<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

use App\Modules\Shared\Application\DTOs\GeneratedReportFile;
use App\Modules\Shared\Domain\Enums\ReportExportStatus;
use App\Modules\Shared\Infrastructure\Jobs\GenerateReportExportJob;
use App\Modules\Shared\Infrastructure\Persistence\Models\ReportExportModel;
use App\Modules\Shared\Infrastructure\Reports\CsvExporter;
use App\Modules\Shared\Infrastructure\Reports\ExcelExporter;
use App\Modules\Shared\Infrastructure\Reports\PdfExporter;
use Illuminate\Contracts\Auth\Authenticatable;

class ReportExportService
{
    public function __construct(
        private readonly PdfExporter $pdfExporter,
        private readonly ExcelExporter $excelExporter,
        private readonly CsvExporter $csvExporter,
    ) {}

    /**
     * Genera el reporte en memoria. Usado tanto por la vía síncrona
     * (descarga inmediata) como por el job en background.
     */
    public function generate(string $reportType, array $filters, string $format): GeneratedReportFile
    {
        return match ($format) {
            'pdf'   => $this->pdfExporter->export($reportType, $filters),
            'excel' => $this->excelExporter->export($reportType, $filters),
            'csv'   => $this->csvExporter->export($reportType, $filters),
            default => throw new \InvalidArgumentException("Formato no soportado: {$format}"),
        };
    }

    /**
     * Registra la solicitud de un reporte pesado y encola su generación
     * en background. El usuario será notificado cuando esté listo.
     */
    public function queue(Authenticatable $user, string $reportType, string $format, array $filters): ReportExportModel
    {
        $export = ReportExportModel::create([
            'user_id' => $user->getAuthIdentifier(),
            'type'    => $reportType,
            'format'  => $format,
            'filters' => $filters,
            'status'  => ReportExportStatus::Queued->value,
        ]);

        GenerateReportExportJob::dispatch($export->id);

        return $export;
    }
}
