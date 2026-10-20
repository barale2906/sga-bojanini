<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Controllers;

use App\Modules\Assets\Application\UseCases\CancelMaintenanceUseCase;
use App\Modules\Assets\Application\UseCases\CompleteMaintenanceUseCase;
use App\Modules\Assets\Application\UseCases\RescheduleMaintenanceUseCase;
use App\Modules\Assets\Application\UseCases\ScheduleMaintenanceUseCase;
use App\Modules\Assets\Application\UseCases\StartMaintenanceUseCase;
use App\Modules\Assets\Infrastructure\Http\Requests\CancelWithReasonRequest;
use App\Modules\Assets\Infrastructure\Http\Requests\CompleteMaintenanceRequest;
use App\Modules\Assets\Infrastructure\Http\Requests\RescheduleMaintenanceRequest;
use App\Modules\Assets\Infrastructure\Http\Requests\ScheduleMaintenanceRequest;
use App\Modules\Assets\Infrastructure\Http\Resources\AssetMaintenanceResource;
use App\Modules\Assets\Infrastructure\Pdf\AssetPdfRenderer;
use App\Modules\Assets\Infrastructure\Queries\AssetMaintenanceQuery;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * @group Activos — Mantenimientos
 *
 * Cronograma y órdenes de mantenimiento: programar, iniciar, registrar la
 * ejecución (quién lo hizo y qué se hizo; soportes opcionales) y cancelar.
 */
class AssetMaintenanceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AssetMaintenanceQuery $query,
    ) {}

    /**
     * Cronograma (paginado). Para la vista de calendario use `from`/`to` y `per_page` alto.
     *
     * @queryParam from date Desde (fecha programada). Example: 2026-10-01
     * @queryParam to date Hasta. Example: 2026-10-31
     * @queryParam status string scheduled | in_progress | completed | cancelled. Example: scheduled
     * @queryParam maintenance_type string Tipo. Example: preventive
     * @queryParam asset_id integer Activo. Example: 1
     * @queryParam site_id integer Sede. Example: 1
     * @queryParam asset_type string biomedical | computing. Example: biomedical
     * @queryParam overdue boolean Solo vencidos. Example: true
     * @queryParam search string Código, nombre o serial del activo. Example: monitor
     * @queryParam per_page integer Máx. 500. Example: 50
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginated(
            $this->query->paginate($request->all(), (int) $request->input('per_page', 50))
                ->through(fn ($maintenance) => new AssetMaintenanceResource($maintenance)),
            'Cronograma de mantenimiento',
        );
    }

    public function show(int $id): JsonResponse
    {
        $maintenance = $this->query->find($id);

        return $maintenance
            ? $this->success(new AssetMaintenanceResource($maintenance), 'Detalle del mantenimiento')
            : $this->error('Mantenimiento no encontrado', 404);
    }

    /** Programar una orden puntual (correctivo o no planificado). */
    public function store(ScheduleMaintenanceRequest $request, int $assetId, ScheduleMaintenanceUseCase $useCase): JsonResponse
    {
        $maintenance = $useCase->execute($assetId, $request->toData(), (int) $request->user()->id);

        return $this->created(new AssetMaintenanceResource($this->query->find($maintenance->id)), 'Mantenimiento programado');
    }

    public function reschedule(RescheduleMaintenanceRequest $request, int $id, RescheduleMaintenanceUseCase $useCase): JsonResponse
    {
        $useCase->execute($id, $request->validated('scheduled_date'), $request->validated('reported_failure'));

        return $this->success(new AssetMaintenanceResource($this->query->find($id)), 'Mantenimiento reprogramado');
    }

    public function start(Request $request, int $id, StartMaintenanceUseCase $useCase): JsonResponse
    {
        $useCase->execute($id, (int) $request->user()->id);

        return $this->success(new AssetMaintenanceResource($this->query->find($id)), 'Mantenimiento iniciado');
    }

    /**
     * Registrar la ejecución.
     *
     * @response 409 {"success": false, "message": "La orden ya fue registrada o cancelada."}
     */
    public function complete(CompleteMaintenanceRequest $request, int $id, CompleteMaintenanceUseCase $useCase): JsonResponse
    {
        $useCase->execute($id, $request->toData(), $request->signers(), (int) $request->user()->id);

        return $this->success(new AssetMaintenanceResource($this->query->find($id)), 'Mantenimiento registrado');
    }

    public function cancel(CancelWithReasonRequest $request, int $id, CancelMaintenanceUseCase $useCase): JsonResponse
    {
        $useCase->execute($id, $request->validated('reason'), (int) $request->user()->id);

        return $this->success(new AssetMaintenanceResource($this->query->find($id)), 'Mantenimiento cancelado');
    }

    /** Reporte del mantenimiento en PDF. */
    public function report(int $id, AssetPdfRenderer $renderer): Response
    {
        $maintenance = $this->query->find($id) ?? abort(404);

        return $renderer->maintenanceReport($maintenance);
    }
}
