<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Controllers;

use App\Modules\CostCenter\Application\DTOs\MedicalServiceData;
use App\Modules\CostCenter\Application\UseCases\CreateMedicalServiceUseCase;
use App\Modules\CostCenter\Application\UseCases\DeleteMedicalServiceUseCase;
use App\Modules\CostCenter\Application\UseCases\GetMedicalServiceTreeUseCase;
use App\Modules\CostCenter\Application\UseCases\ListMedicalServicesUseCase;
use App\Modules\CostCenter\Application\UseCases\ListProceduresByServiceUseCase;
use App\Modules\CostCenter\Application\UseCases\UpdateMedicalServiceUseCase;
use App\Modules\CostCenter\Domain\Enums\MedicalServiceType;
use App\Modules\CostCenter\Infrastructure\Http\Requests\StoreMedicalServiceRequest;
use App\Modules\CostCenter\Infrastructure\Http\Requests\UpdateMedicalServiceRequest;
use App\Modules\CostCenter\Infrastructure\Http\Resources\MedicalServiceResource;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group Servicios Médicos y Procedimientos
 *
 * Catálogo de servicios médicos y sus procedimientos en árbol.
 * Los servicios son nodos raíz; los procedimientos son hijos directos de un servicio.
 * Solo los procedimientos pueden tener tarifas y registros de paciente.
 */
class MedicalServiceController extends Controller
{
    use ApiResponse;

    /**
     * Listar servicios médicos y/o procedimientos (plano).
     *
     * @queryParam type string Filtrar por tipo: service | procedure. Example: procedure
     * @queryParam parent_id integer Filtrar por servicio padre. Example: 1
     * @queryParam is_active boolean Filtrar por estado activo. Example: true
     * @queryParam search string Buscar por código o nombre. Example: cirugia
     *
     * @response 200 {"success": true, "message": "Listado de servicios médicos", "data": [...]}
     */
    public function index(Request $request, ListMedicalServicesUseCase $useCase): JsonResponse
    {
        $filters = $request->only(['type', 'parent_id', 'is_active', 'search']);
        $items   = $useCase->execute($filters);

        return $this->success(
            MedicalServiceResource::collection($items),
            'Listado de servicios médicos',
        );
    }

    /**
     * Árbol de servicios con sus procedimientos anidados.
     *
     * @queryParam only_active boolean Incluir solo activos. Example: true
     *
     * @response 200 {"success": true, "data": [{"id":1,"type":"service","children":[{"id":2,"type":"procedure",...}]}]}
     */
    public function tree(Request $request, GetMedicalServiceTreeUseCase $useCase): JsonResponse
    {
        $onlyActive = filter_var($request->query('only_active', false), FILTER_VALIDATE_BOOLEAN);
        $tree       = $useCase->execute($onlyActive);

        return $this->success(
            MedicalServiceResource::collection(collect($tree)),
            'Árbol de servicios médicos',
        );
    }

    /**
     * Listar procedimientos de un servicio.
     *
     * @urlParam medical_service integer required ID del servicio padre. Example: 1
     *
     * @response 200 {"success": true, "data": [{"id": 2, "type": "procedure", ...}]}
     * @response 409 {"success": false, "message": "Servicio médico con id 99 no encontrado."}
     */
    public function procedures(int $medical_service, ListProceduresByServiceUseCase $useCase): JsonResponse
    {
        $items = $useCase->execute($medical_service);

        return $this->success(
            MedicalServiceResource::collection($items),
            'Procedimientos del servicio',
        );
    }

    /**
     * Obtener un servicio médico o procedimiento.
     *
     * @urlParam id integer required ID. Example: 1
     *
     * @response 200 {"success": true, "data": {"id": 1, "type": "service", ...}}
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
     * Crear un servicio médico o procedimiento.
     *
     * @bodyParam type string Tipo: service (default) | procedure. Example: procedure
     * @bodyParam parent_id integer ID del servicio padre (requerido si type=procedure). Example: 1
     * @bodyParam code string required Código único (máx. 20 caracteres). Example: CUR-001
     * @bodyParam name string required Nombre. Example: Curaciones simples
     * @bodyParam description string Descripción opcional.
     * @bodyParam is_active boolean Estado inicial (default: true). Example: true
     *
     * @response 201 {"success": true, "data": {"id": 2, "type": "procedure", "parent_id": 1, ...}}
     * @response 409 {"success": false, "message": "Ya existe un servicio médico con el código 'CUR-001'."}
     * @response 422 {"success": false, "errors": {...}}
     */
    public function store(StoreMedicalServiceRequest $request, CreateMedicalServiceUseCase $useCase): JsonResponse
    {
        $validated = $request->validated();

        $service = $useCase->execute(new MedicalServiceData(
            code:         $validated['code'],
            name:         $validated['name'],
            description:  $validated['description'] ?? null,
            type:         MedicalServiceType::from($validated['type'] ?? 'service'),
            parentId:     isset($validated['parent_id']) ? (int) $validated['parent_id'] : null,
            isActive:     (bool) ($validated['is_active'] ?? true),
            externalCode: $validated['external_code'] ?? null,
        ));

        return $this->created(new MedicalServiceResource($service), 'Servicio médico creado exitosamente');
    }

    /**
     * Actualizar un servicio médico o procedimiento.
     *
     * @urlParam medical_service integer required ID. Example: 1
     *
     * @response 200 {"success": true, "data": {"id": 1, ...}}
     * @response 409 {"success": false, "message": "No se puede cambiar el tipo..."}
     */
    public function update(UpdateMedicalServiceRequest $request, int $medical_service, UpdateMedicalServiceUseCase $useCase): JsonResponse
    {
        $validated = $request->validated();

        $service = $useCase->execute($medical_service, new MedicalServiceData(
            code:         $validated['code'],
            name:         $validated['name'],
            description:  $validated['description'] ?? null,
            type:         MedicalServiceType::from($validated['type'] ?? 'service'),
            parentId:     isset($validated['parent_id']) ? (int) $validated['parent_id'] : null,
            isActive:     (bool) ($validated['is_active'] ?? true),
            externalCode: $validated['external_code'] ?? null,
        ));

        return $this->success(new MedicalServiceResource($service), 'Servicio médico actualizado exitosamente');
    }

    /**
     * Eliminar un servicio médico o procedimiento (soft delete).
     *
     * @urlParam medical_service integer required ID. Example: 1
     *
     * @response 200 {"success": true, "message": "Servicio médico eliminado"}
     * @response 409 {"success": false, "message": "No se puede eliminar el servicio porque tiene procedimientos asociados."}
     */
    public function destroy(int $medical_service, DeleteMedicalServiceUseCase $useCase): JsonResponse
    {
        $useCase->execute($medical_service);

        return $this->noContent('Servicio médico eliminado');
    }
}
