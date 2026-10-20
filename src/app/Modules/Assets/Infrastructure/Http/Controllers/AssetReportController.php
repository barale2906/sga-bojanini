<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Controllers;

use App\Modules\Assets\Infrastructure\Export\AssetInventoryExport;
use App\Modules\Assets\Infrastructure\Export\MaintenanceScheduleExport;
use App\Modules\Assets\Infrastructure\Pdf\AssetPdfRenderer;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;
use App\Modules\Assets\Infrastructure\Queries\AssetMaintenanceQuery;
use App\Modules\Assets\Infrastructure\Queries\AssetQuery;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @group Activos — Reportes y etiquetas
 */
class AssetReportController extends Controller
{
    use ApiResponse;

    /** Máximo de etiquetas por solicitud. */
    private const MAX_LABELS = 200;

    /** Inventario de activos en Excel (acepta los mismos filtros del listado). */
    public function inventory(Request $request, AssetQuery $query): BinaryFileResponse
    {
        return Excel::download(new AssetInventoryExport($query->all($request->all())), 'inventario_activos_' . now()->format('Ymd') . '.xlsx');
    }

    /** Cronograma de mantenimiento en Excel (acepta los filtros del cronograma). */
    public function maintenanceSchedule(Request $request, AssetMaintenanceQuery $query): BinaryFileResponse
    {
        return Excel::download(new MaintenanceScheduleExport($query->all($request->all())), 'cronograma_mantenimiento_' . now()->format('Ymd') . '.xlsx');
    }

    /**
     * Etiquetas con código de barras Code128 en PDF.
     *
     * @queryParam ids string IDs separados por coma; si se omite usa los filtros del listado. Example: 1,2,3
     */
    public function labels(Request $request, AssetQuery $query, AssetPdfRenderer $renderer): Response|JsonResponse
    {
        $assets = $request->filled('ids')
            ? AssetModel::with('currentPlace.site')->whereIn('id', explode(',', (string) $request->input('ids')))->orderBy('code')->get()
            : $query->all($request->all());

        if ($assets->isEmpty()) {
            return $this->error('No hay activos para imprimir.', 404);
        }

        if ($assets->count() > self::MAX_LABELS) {
            return $this->error('Máximo ' . self::MAX_LABELS . ' etiquetas por impresión; aplique más filtros.', 422);
        }

        return $renderer->labels($assets);
    }
}
