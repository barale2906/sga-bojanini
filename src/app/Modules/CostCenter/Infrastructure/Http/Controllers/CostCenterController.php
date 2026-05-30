<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Controllers;

use App\Modules\CostCenter\Application\DTOs\CostCenterData;
use App\Modules\CostCenter\Application\UseCases\CreateCostCenterUseCase;
use App\Modules\CostCenter\Application\UseCases\DeleteCostCenterUseCase;
use App\Modules\CostCenter\Application\UseCases\ListCostCentersUseCase;
use App\Modules\CostCenter\Application\UseCases\UpdateCostCenterUseCase;
use App\Modules\CostCenter\Infrastructure\Http\Requests\StoreCostCenterRequest;
use App\Modules\CostCenter\Infrastructure\Http\Requests\UpdateCostCenterRequest;
use App\Modules\CostCenter\Infrastructure\Http\Resources\CostCenterResource;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\CostCenterModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group Centros de Costo
 *
 * Gestión de centros de costo. Un centro puede ser interno (área de la empresa)
 * o externo (atención a pacientes). Todo movimiento de salida de inventario
 * debe estar asociado a un centro de costo activo.
 */
class CostCenterController extends Controller
{
    use ApiResponse;

    /**
     * Listar centros de costo.
     *
     * @queryParam type string Filtrar por tipo: internal | external. Example: internal
     * @queryParam is_active boolean Filtrar por estado activo. Example: true
     * @queryParam search string Buscar por código o nombre. Example: farmacia
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Listado de centros de costo",
     *   "data": [{"id": 1, "code": "FARM", "name": "Farmacia", "type": "internal", ...}]
     * }
     */
    public function index(Request $request, ListCostCentersUseCase $useCase): JsonResponse
    {
        $filters = $request->only(['type', 'is_active', 'search']);
        $items   = $useCase->execute($filters);

        return $this->success(
            CostCenterResource::collection($items),
            'Listado de centros de costo',
        );
    }

    /**
     * Obtener un centro de costo.
     *
     * @urlParam id integer required ID del centro de costo. Example: 1
     *
     * @response 200 {"success": true, "data": {"id": 1, "code": "FARM", ...}}
     * @response 404 {"success": false, "message": "Recurso no encontrado."}
     */
    public function show(int $id): JsonResponse
    {
        $model = CostCenterModel::find($id);

        if ($model === null) {
            return $this->error('Centro de costo no encontrado', 404);
        }

        return $this->success(new CostCenterResource($model), 'Detalle del centro de costo');
    }

    /**
     * Crear un centro de costo.
     *
     * @bodyParam code string required Código único (máx. 20 caracteres). Example: FARM
     * @bodyParam name string required Nombre descriptivo. Example: Farmacia Interna
     * @bodyParam type string required Tipo: internal | external. Example: internal
     * @bodyParam description string Descripción opcional. Example: Área de dispensación interna
     * @bodyParam is_active boolean Estado inicial (default: true). Example: true
     *
     * @response 201 {"success": true, "data": {"id": 1, "code": "FARM", ...}}
     * @response 409 {"success": false, "message": "Ya existe un centro de costo con el código 'FARM'."}
     * @response 422 {"success": false, "message": "Error de validación.", "errors": {...}}
     */
    public function store(StoreCostCenterRequest $request, CreateCostCenterUseCase $useCase): JsonResponse
    {
        $validated = $request->validated();

        $costCenter = $useCase->execute(new CostCenterData(
            code:        $validated['code'],
            name:        $validated['name'],
            type:        $validated['type'],
            description: $validated['description'] ?? null,
            isActive:    (bool) ($validated['is_active'] ?? true),
        ));

        return $this->created(new CostCenterResource($costCenter), 'Centro de costo creado exitosamente');
    }

    /**
     * Actualizar un centro de costo.
     *
     * @urlParam cost_center integer required ID del centro de costo. Example: 1
     * @bodyParam code string required Código único. Example: FARM
     * @bodyParam name string required Nombre. Example: Farmacia Interna
     * @bodyParam type string required Tipo: internal | external. Example: internal
     * @bodyParam description string Descripción. Example: null
     * @bodyParam is_active boolean Estado. Example: true
     *
     * @response 200 {"success": true, "data": {"id": 1, ...}}
     * @response 409 {"success": false, "message": "Centro de costo con id 99 no encontrado."}
     */
    public function update(UpdateCostCenterRequest $request, int $cost_center, UpdateCostCenterUseCase $useCase): JsonResponse
    {
        $validated = $request->validated();

        $costCenter = $useCase->execute($cost_center, new CostCenterData(
            code:        $validated['code'],
            name:        $validated['name'],
            type:        $validated['type'],
            description: $validated['description'] ?? null,
            isActive:    (bool) ($validated['is_active'] ?? true),
        ));

        return $this->success(new CostCenterResource($costCenter), 'Centro de costo actualizado exitosamente');
    }

    /**
     * Eliminar un centro de costo.
     *
     * @urlParam cost_center integer required ID del centro de costo. Example: 1
     *
     * @response 200 {"success": true, "message": "Centro de costo eliminado"}
     * @response 409 {"success": false, "message": "No se puede eliminar el centro de costo porque tiene movimientos asociados."}
     */
    public function destroy(int $cost_center, DeleteCostCenterUseCase $useCase): JsonResponse
    {
        $useCase->execute($cost_center);

        return $this->noContent('Centro de costo eliminado');
    }
}
