<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Http\Controllers;

use App\Modules\Shared\Application\Services\ReportExportService;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ReportController extends Controller
{
    use ApiResponse;

    public function inventory(Request $request, ReportExportService $service): JsonResponse
    {
        return $this->exportResponse($service, 'inventory', $request->all());
    }

    public function movements(Request $request, ReportExportService $service): JsonResponse
    {
        return $this->exportResponse($service, 'movements', $request->all());
    }

    public function expiring(Request $request, ReportExportService $service): JsonResponse
    {
        return $this->exportResponse($service, 'expiring', $request->all());
    }

    public function purchases(Request $request, ReportExportService $service): JsonResponse
    {
        return $this->exportResponse($service, 'purchases', $request->all());
    }

    public function consumption(Request $request, ReportExportService $service): JsonResponse
    {
        return $this->exportResponse($service, 'consumption', $request->all());
    }

    public function conditions(Request $request, ReportExportService $service): JsonResponse
    {
        return $this->exportResponse($service, 'conditions', $request->all());
    }

    public function audit(Request $request, ReportExportService $service): JsonResponse
    {
        return $this->exportResponse($service, 'audit', $request->all());
    }

    private function exportResponse(ReportExportService $service, string $type, array $filters): JsonResponse
    {
        $format = $filters['format'] ?? 'pdf';

        if (!in_array($format, ['pdf', 'excel', 'csv'], true)) {
            return $this->error('Formato no válido. Use pdf, excel o csv.', 422);
        }

        $path = $service->generate($type, $filters, $format);

        return $this->success([
            'path'     => $path,
            'filename' => basename($path),
            'format'   => $format,
        ], 'Reporte generado correctamente', 201);
    }
}
