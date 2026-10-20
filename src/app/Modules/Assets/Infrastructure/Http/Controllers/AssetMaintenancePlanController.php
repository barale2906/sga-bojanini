<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Controllers;

use App\Modules\Assets\Application\UseCases\DeleteMaintenancePlanUseCase;
use App\Modules\Assets\Application\UseCases\SaveMaintenancePlanUseCase;
use App\Modules\Assets\Infrastructure\Http\Requests\MaintenancePlanRequest;
use App\Modules\Assets\Infrastructure\Http\Resources\AssetMaintenancePlanResource;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenancePlanModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group Activos — Planes de mantenimiento
 *
 * Planes periódicos (preventivo, calibración, verificación, inspección).
 * Cada plan activo mantiene una orden abierta en el cronograma.
 */
class AssetMaintenancePlanController extends Controller
{
    use ApiResponse;

    private const RELATIONS = ['technician', 'supplier', 'openOrder'];

    public function index(int $assetId): JsonResponse
    {
        AssetModel::withTrashed()->findOrFail($assetId);

        $plans = AssetMaintenancePlanModel::with(self::RELATIONS)
            ->where('asset_id', $assetId)
            ->orderByDesc('is_active')->orderBy('maintenance_type')
            ->get();

        return $this->success(AssetMaintenancePlanResource::collection($plans), 'Planes de mantenimiento');
    }

    public function store(MaintenancePlanRequest $request, int $assetId, SaveMaintenancePlanUseCase $useCase): JsonResponse
    {
        $plan = $useCase->execute($assetId, null, $request->toData(), (int) $request->user()->id);

        return $this->created(new AssetMaintenancePlanResource($plan->load(self::RELATIONS)), 'Plan de mantenimiento creado');
    }

    public function update(MaintenancePlanRequest $request, int $planId, SaveMaintenancePlanUseCase $useCase): JsonResponse
    {
        $assetId = AssetMaintenancePlanModel::findOrFail($planId)->asset_id;
        $plan    = $useCase->execute($assetId, $planId, $request->toData(), (int) $request->user()->id);

        return $this->success(new AssetMaintenancePlanResource($plan->load(self::RELATIONS)), 'Plan de mantenimiento actualizado');
    }

    public function destroy(Request $request, int $planId, DeleteMaintenancePlanUseCase $useCase): JsonResponse
    {
        $useCase->execute($planId, (int) $request->user()->id);

        return $this->noContent('Plan de mantenimiento eliminado');
    }
}
