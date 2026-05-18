<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\Http\Controllers;

use App\Modules\Integration\Domain\Ports\ClinicalRecordsServiceInterface;
use App\Modules\Integration\Domain\Ports\SchedulingServiceInterface;
use App\Modules\Integration\Domain\Repositories\ExternalIntegrationRepositoryInterface;
use App\Modules\Integration\Infrastructure\Http\Requests\StoreIntegrationRequest;
use App\Modules\Integration\Infrastructure\Http\Resources\ExternalIntegrationResource;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IntegrationController extends Controller
{
    use ApiResponse;

    public function index(ExternalIntegrationRepositoryInterface $repository): JsonResponse
    {
        return $this->success(
            ExternalIntegrationResource::collection($repository->findAll()),
            'Listado de integraciones',
        );
    }

    public function store(StoreIntegrationRequest $request, ExternalIntegrationRepositoryInterface $repository): JsonResponse
    {
        $integration = $repository->save($request->validated());

        return $this->created(
            new ExternalIntegrationResource($integration),
            'Integración configurada correctamente',
        );
    }

    public function show(int $id, ExternalIntegrationRepositoryInterface $repository): JsonResponse
    {
        $integration = $repository->findById($id);

        if ($integration === null) {
            return $this->error('Integración no encontrada', 404);
        }

        return $this->success(new ExternalIntegrationResource($integration));
    }

    public function update(int $id, StoreIntegrationRequest $request, ExternalIntegrationRepositoryInterface $repository): JsonResponse
    {
        $integration = $repository->save($request->validated(), $id);

        return $this->success(new ExternalIntegrationResource($integration), 'Integración actualizada');
    }

    public function destroy(int $id, ExternalIntegrationRepositoryInterface $repository): JsonResponse
    {
        $repository->delete($id);

        return $this->success(null, 'Integración eliminada');
    }

    public function testConnection(
        int $id,
        ExternalIntegrationRepositoryInterface $repository,
        SchedulingServiceInterface $schedulingService,
        ClinicalRecordsServiceInterface $clinicalService,
    ): JsonResponse {
        $integration = $repository->findById($id);

        if ($integration === null) {
            return $this->error('Integración no encontrada', 404);
        }

        try {
            if ($integration->type === 'scheduling') {
                $appointments = $schedulingService->getUpcomingAppointments(
                    Carbon::today(),
                    Carbon::today()->addDay(),
                );
                $message = 'Conexión exitosa. Citas obtenidas: '.count($appointments);
            } else {
                $result = $clinicalService->registerConsumption('TEST-PAC', 'TEST-CITA', []);
                $message = $result['message'] ?? 'Conexión exitosa con historias clínicas';
            }

            $integration->update(['last_sync_at' => now()]);

            return $this->success(['message' => $message], 'Prueba de conexión exitosa');
        } catch (\Throwable $e) {
            return $this->error('Error de conexión: '.$e->getMessage(), 502);
        }
    }
}
