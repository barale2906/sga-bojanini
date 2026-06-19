<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Contracts\Auth\Authenticatable;
use Symfony\Component\HttpFoundation\Response;

/**
 * Punto de entrada único para "generar un reporte" desde el módulo de
 * Reportes. Decide, según el volumen estimado de datos, si conviene:
 *
 * - Generarlo ya mismo y devolverlo como descarga directa (streaming, sin
 *   tocar disco), o
 * - Encolarlo en background y devolver 202 con el identificador de
 *   seguimiento, para que el usuario sea notificado cuando esté listo.
 */
class ReportRequestHandler
{
    use ApiResponse;

    public function __construct(
        private readonly ReportDataCollector $dataCollector,
        private readonly ReportExportService $exportService,
    ) {}

    public function handle(Authenticatable $user, string $reportType, array $filters): Response
    {
        $format = $filters['format'] ?? 'pdf';
        $threshold = (int) config('sga.reports.async_row_threshold', 1000);
        $estimatedRows = $this->dataCollector->estimateCount($reportType, $filters);

        if ($estimatedRows > $threshold) {
            $export = $this->exportService->queue($user, $reportType, $format, $filters);

            return $this->success([
                'mode'      => 'async',
                'export_id' => $export->id,
                'status'    => $export->status,
            ], 'El reporte es muy extenso y se está generando en segundo plano. Te notificaremos cuando esté listo.', 202);
        }

        $file = $this->exportService->generate($reportType, $filters, $format);

        return $file->toDownloadResponse();
    }
}
