<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Http\Controllers;

use App\Modules\Audit\Domain\Repositories\AuditLogRepositoryInterface;
use App\Modules\Audit\Infrastructure\Http\Resources\AuditLogResource;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controller para consultar registros de auditoría.
 */
class AuditLogController extends Controller
{
    use ApiResponse;

    public function index(Request $request, AuditLogRepositoryInterface $repository): JsonResponse
    {
        $result = $repository->findAll([
            'user_id'        => $request->query('user_id'),
            'action'         => $request->query('action'),
            'auditable_type' => $request->query('auditable_type'),
            'date_from'      => $request->query('date_from'),
            'date_to'        => $request->query('date_to'),
            'page'           => $request->query('page'),
            'per_page'       => $request->query('per_page'),
        ]);

        return $this->success([
            'items' => AuditLogResource::collection($result['data']),
            'meta'  => $result['meta'],
        ], 'Listado de registros de auditoría');
    }

    public function show(int $id, AuditLogRepositoryInterface $repository): JsonResponse
    {
        $log = $repository->findById($id);

        if ($log === null) {
            return $this->error('Registro de auditoría no encontrado.', 404);
        }

        return $this->success(new AuditLogResource($log), 'Detalle del registro de auditoría');
    }

    public function export(Request $request): JsonResponse
    {
        return $this->success([], 'Exportación en desarrollo.');
    }

    public function summary(Request $request, AuditLogRepositoryInterface $repository): JsonResponse
    {
        $from = Carbon::parse($request->query('date_from', now()->subDays(30)->toDateString()));
        $to   = Carbon::parse($request->query('date_to', now()->toDateString()));

        $summary = $repository->getSummary($from, $to);

        return $this->success($summary, 'Resumen de auditoría');
    }
}
