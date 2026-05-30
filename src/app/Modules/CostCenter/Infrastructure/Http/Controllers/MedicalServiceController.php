<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Controllers;

use App\Modules\CostCenter\Application\DTOs\MedicalServiceData;
use App\Modules\CostCenter\Application\UseCases\CreateMedicalServiceUseCase;
use App\Modules\CostCenter\Application\UseCases\DeleteMedicalServiceUseCase;
use App\Modules\CostCenter\Application\UseCases\ListMedicalServicesUseCase;
use App\Modules\CostCenter\Application\UseCases\UpdateMedicalServiceUseCase;
use App\Modules\CostCenter\Infrastructure\Http\Requests\StoreMedicalServiceRequest;
use App\Modules\CostCenter\Infrastructure\Http\Requests\UpdateMedicalServiceRequest;
use App\Modules\CostCenter\Infrastructure\Http\Resources\MedicalServiceResource;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group Servicios Médicos
 *
 * Catálogo de servicios médicos prestados. Se asocian a los movimientos de salida
 * cuyo centro de costo es externo (pacientes), registrando qué procedimiento
 * motivó el consumo del insumo.
 */
class MedicalServiceController extends Controller
{
    use ApiResponse;

    /**
     * Listar servicios médicos.
     *
     * @queryParam is_active boolean Filtrar por estado activo. Example: true
     * @queryParam search string Buscar por código o nombre. Example: cirugia
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Listado de servicios médicos",
     *   "data": [{"id": 1, "code": "CIR", "name": "Cirugía General", ...}]
     * }
     */
    public function index(Request $request, ListMedicalServicesUseCase $useCase): JsonResponse
    {
        $filters = $request->only(['is_active', 'search']);
        $items   = $useCase->execute($filters);

        return $this->success(
            MedicalServiceResource::collection($items),
            'Listado de servicios médicos',
        );
    }

    /**
     * Obtener un servicio médico.
     *
     * @urlParam id integer required ID del servicio. Example: 1
     *
     * @response 200 {"success": true, "data": {"id": 1, "code": "CIR", ...}}
     * @response 404 {"success": false, "message": "Recurso no encontrado."}
     */
    public function show(int $id): JsonResponse
    {
        $model = MedicalServiceModel::find($id);

        if ($model === null) {
            return $this->error('Servicio médico no encontrado', 404);
        }

        return $this->success(new MedicalServiceResource($model), 'Detalle del servicio médico');
    }

    /**
     * Crear un servicio médico.
     *
     * @bodyParam code string required Código único (máx. 20 caracteres). Example: CIR
     * @bodyParam name string required Nombre del servicio. Example: Cirugía General
     * @bodyParam description string Descripción opcional. Example: Procedimientos quirúrgicos generales
     * @bodyParam is_active boolean Estado inicial (default: true). Example: true
     *
     * @response 201 {"success": true, "data": {"id": 1, "code": "CIR", ...}}
     * @response 409 {"success": false, "message": "Ya existe un servicio médico con el código 'CIR'."}
     * @response 422 {"success": false, "message": "Error de validación.", "errors": {...}}
     */
    public function store(StoreMedicalServiceRequest $request, CreateMedicalServiceUseCase $useCase): JsonResponse
    {
        $validated = $request->validated();

        $service = $useCase->execute(new MedicalServiceData(
            code:        $validated['code'],
            name:        $validated['name'],
            description: $validated['description'] ?? null,
            isActive:    (bool) ($validated['is_active'] ?? true),
        ));

        return $this->created(new MedicalServiceResource($service), 'Servicio médico creado exitosamente');
    }

    /**
     * Actualizar un servicio médico.
     *
     * @urlParam medical_service integer required ID del servicio. Example: 1
     * @bodyParam code string required Código único. Example: CIR
     * @bodyParam name string required Nombre. Example: Cirugía General
     * @bodyParam description string Descripción. Example: null
     * @bodyParam is_active boolean Estado. Example: true
     *
     * @response 200 {"success": true, "data": {"id": 1, ...}}
     * @response 409 {"success": false, "message": "Servicio médico con id 99 no encontrado."}
     */
    public function update(UpdateMedicalServiceRequest $request, int $medical_service, UpdateMedicalServiceUseCase $useCase): JsonResponse
    {
        $validated = $request->validated();

        $service = $useCase->execute($medical_service, new MedicalServiceData(
            code:        $validated['code'],
            name:        $validated['name'],
            description: $validated['description'] ?? null,
            isActive:    (bool) ($validated['is_active'] ?? true),
        ));

        return $this->success(new MedicalServiceResource($service), 'Servicio médico actualizado exitosamente');
    }

    /**
     * Eliminar un servicio médico.
     *
     * @urlParam medical_service integer required ID del servicio. Example: 1
     *
     * @response 200 {"success": true, "message": "Servicio médico eliminado"}
     * @response 409 {"success": false, "message": "No se puede eliminar el servicio porque tiene movimientos de inventario asociados."}
     */
    public function destroy(int $medical_service, DeleteMedicalServiceUseCase $useCase): JsonResponse
    {
        $useCase->execute($medical_service);

        return $this->noContent('Servicio médico eliminado');
    }
}
