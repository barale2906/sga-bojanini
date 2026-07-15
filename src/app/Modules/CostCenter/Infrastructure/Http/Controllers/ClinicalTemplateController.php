<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Controllers;

use App\Modules\CostCenter\Application\DTOs\ClinicalTemplateData;
use App\Modules\CostCenter\Application\UseCases\CreateClinicalTemplateUseCase;
use App\Modules\CostCenter\Application\UseCases\DeleteClinicalTemplateUseCase;
use App\Modules\CostCenter\Application\UseCases\FindTemplateByServiceUseCase;
use App\Modules\CostCenter\Application\UseCases\ListClinicalTemplatesUseCase;
use App\Modules\CostCenter\Application\UseCases\UpdateClinicalTemplateUseCase;
use App\Modules\CostCenter\Infrastructure\Http\Requests\StoreClinicalTemplateRequest;
use App\Modules\CostCenter\Infrastructure\Http\Requests\UpdateClinicalTemplateRequest;
use App\Modules\CostCenter\Infrastructure\Http\Resources\ClinicalTemplateResource;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\ClinicalTemplateModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ClinicalTemplateController extends Controller
{
    use ApiResponse;

    public function index(Request $request, ListClinicalTemplatesUseCase $useCase): JsonResponse
    {
        $filters = $request->only(['medical_service_id', 'is_active']);
        $items   = $useCase->execute($filters);

        return $this->success(
            ClinicalTemplateResource::collection($items),
            'Plantillas de evolución clínica',
        );
    }

    public function show(int $id): JsonResponse
    {
        $model = ClinicalTemplateModel::with('medicalService')->find($id);

        if ($model === null) {
            return $this->error('Plantilla no encontrada', 404);
        }

        return $this->success(new ClinicalTemplateResource($model), 'Detalle de plantilla');
    }

    public function forService(int $medicalServiceId, FindTemplateByServiceUseCase $useCase): JsonResponse
    {
        $template = $useCase->execute($medicalServiceId);

        if ($template === null) {
            return $this->success(null, 'Sin plantilla para este servicio/procedimiento');
        }

        return $this->success(new ClinicalTemplateResource($template), 'Plantilla aplicable');
    }

    public function store(StoreClinicalTemplateRequest $request, CreateClinicalTemplateUseCase $useCase): JsonResponse
    {
        $validated = $request->validated();

        $template = $useCase->execute(new ClinicalTemplateData(
            medicalServiceId: (int) $validated['medical_service_id'],
            title:            $validated['title'],
            content:          $validated['content'],
        ));

        return $this->created(new ClinicalTemplateResource($template), 'Plantilla creada exitosamente');
    }

    public function update(UpdateClinicalTemplateRequest $request, int $id, UpdateClinicalTemplateUseCase $useCase): JsonResponse
    {
        $validated = $request->validated();

        $template = $useCase->execute($id, new ClinicalTemplateData(
            medicalServiceId: 0,
            title:            $validated['title'],
            content:          $validated['content'],
            isActive:         (bool) ($validated['is_active'] ?? true),
        ));

        return $this->success(new ClinicalTemplateResource($template), 'Plantilla actualizada exitosamente');
    }

    public function destroy(int $id, DeleteClinicalTemplateUseCase $useCase): JsonResponse
    {
        $useCase->execute($id);

        return $this->noContent('Plantilla eliminada');
    }
}
