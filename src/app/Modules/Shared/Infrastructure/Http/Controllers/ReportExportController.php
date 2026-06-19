<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Http\Controllers;

use App\Modules\Shared\Domain\Enums\ReportExportStatus;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Shared\Infrastructure\Persistence\Models\ReportExportModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gestiona el ciclo de vida de los reportes generados en background:
 * listarlos, consultar su estado (para polling desde el frontend) y
 * descargarlos una vez están listos.
 */
class ReportExportController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 25), 1), 100);

        $exports = ReportExportModel::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $exports->setCollection(
            $exports->getCollection()->map(fn (ReportExportModel $export) => $this->toArray($export)),
        );

        return $this->paginated($exports);
    }

    public function show(string $id): JsonResponse
    {
        $export = ReportExportModel::where('user_id', Auth::id())->findOrFail($id);

        return $this->success($this->toArray($export));
    }

    public function download(string $id): StreamedResponse|JsonResponse
    {
        $export = ReportExportModel::where('user_id', Auth::id())->findOrFail($id);

        if (!$export->isReady()) {
            return $this->error('El reporte aún no está listo para descargar.', 409);
        }

        if ($export->isExpired()) {
            return $this->error('El reporte expiró. Genéralo nuevamente desde Reportes.', 410);
        }

        $disk = Storage::disk($export->file_disk);

        if ($export->file_path === null || !$disk->exists($export->file_path)) {
            return $this->error('El archivo del reporte ya no está disponible.', 404);
        }

        $extension = pathinfo($export->file_path, PATHINFO_EXTENSION);
        $downloadName = sprintf('%s_%s.%s', $export->type, $export->created_at->format('Ymd_His'), $extension);

        return $disk->download($export->file_path, $downloadName);
    }

    private function toArray(ReportExportModel $export): array
    {
        return [
            'id'            => $export->id,
            'type'          => $export->type,
            'format'        => $export->format,
            'status'        => $export->status,
            'is_ready'      => $export->isReady(),
            'is_expired'    => $export->isExpired(),
            'file_size'     => $export->file_size,
            'error_message' => $export->status === ReportExportStatus::Failed->value ? $export->error_message : null,
            'expires_at'    => $export->expires_at?->toIso8601String(),
            'created_at'    => $export->created_at->toIso8601String(),
            'completed_at'  => $export->completed_at?->toIso8601String(),
            'download_url'  => $export->isReady() && !$export->isExpired()
                ? url("/api/v1/reports/exports/{$export->id}/download")
                : null,
        ];
    }
}
