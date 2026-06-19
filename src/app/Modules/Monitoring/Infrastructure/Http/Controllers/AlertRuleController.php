<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Controllers;

use App\Modules\Monitoring\Application\DTOs\AlertRuleData;
use App\Modules\Monitoring\Application\UseCases\CreateAlertRuleUseCase;
use App\Modules\Monitoring\Application\UseCases\DeleteAlertRuleUseCase;
use App\Modules\Monitoring\Application\UseCases\ListAlertRulesUseCase;
use App\Modules\Monitoring\Application\UseCases\UpdateAlertRuleUseCase;
use App\Modules\Monitoring\Domain\Repositories\AlertRuleRepositoryInterface;
use App\Modules\Monitoring\Infrastructure\Http\Requests\StoreAlertRuleRequest;
use App\Modules\Monitoring\Infrastructure\Http\Requests\UpdateAlertRuleRequest;
use App\Modules\Monitoring\Infrastructure\Http\Resources\AlertRuleResource;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Shared\Infrastructure\Http\Traits\ChecksSensorAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AlertRuleController extends Controller
{
    use ApiResponse;
    use ChecksSensorAccess;

    public function index(int $sensorId, Request $request, ListAlertRulesUseCase $useCase): JsonResponse
    {
        $this->assertSensorAccess($request->user(), $sensorId);

        $rules = $useCase->execute($sensorId);

        return $this->success(AlertRuleResource::collection($rules));
    }

    public function store(int $sensorId, StoreAlertRuleRequest $request, CreateAlertRuleUseCase $useCase): JsonResponse
    {
        $this->assertSensorAccess($request->user(), $sensorId);

        $data = new AlertRuleData(
            sensorId: $sensorId,
            conditionType: $request->validated('condition_type'),
            threshold: $request->has('threshold') ? (float) $request->validated('threshold') : null,
            consecutiveReadings: (int) ($request->validated('consecutive_readings') ?? 1),
            notificationChannels: $request->validated('notification_channels'),
        );

        $rule = $useCase->execute($data);

        return $this->created(new AlertRuleResource($rule), 'Regla de alerta creada');
    }

    public function update(int $id, UpdateAlertRuleRequest $request, UpdateAlertRuleUseCase $useCase, AlertRuleRepositoryInterface $repository): JsonResponse
    {
        $existing = $repository->findById($id);
        if ($existing === null) {
            return $this->error('Regla de alerta no encontrada', 404);
        }

        $this->assertSensorAccess($request->user(), $existing->getSensorId());

        $data = new AlertRuleData(
            sensorId: $existing->getSensorId(),
            conditionType: $request->validated('condition_type') ?? $existing->getConditionType(),
            threshold: $request->has('threshold')
                ? ($request->validated('threshold') !== null ? (float) $request->validated('threshold') : null)
                : $existing->getThreshold(),
            consecutiveReadings: (int) ($request->validated('consecutive_readings') ?? $existing->getConsecutiveReadings()),
            notificationChannels: $request->validated('notification_channels') ?? $existing->getNotificationChannels(),
            isActive: $request->has('is_active')
                ? (bool) $request->validated('is_active')
                : $existing->isActive(),
        );

        $rule = $useCase->execute($id, $data);

        return $this->success(new AlertRuleResource($rule), 'Regla de alerta actualizada');
    }

    public function destroy(int $id, Request $request, DeleteAlertRuleUseCase $useCase, AlertRuleRepositoryInterface $repository): JsonResponse
    {
        $existing = $repository->findById($id);
        if ($existing === null) {
            return $this->error('Regla de alerta no encontrada', 404);
        }

        $this->assertSensorAccess($request->user(), $existing->getSensorId());

        $useCase->execute($id);

        return $this->noContent('Regla de alerta eliminada');
    }
}
