<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Controllers;

use App\Modules\CostCenter\Application\DTOs\ProcedurePriceData;
use App\Modules\CostCenter\Application\UseCases\CreateProcedurePriceUseCase;
use App\Modules\CostCenter\Application\UseCases\DeleteProcedurePriceUseCase;
use App\Modules\CostCenter\Application\UseCases\ListProcedurePricesUseCase;
use App\Modules\CostCenter\Application\UseCases\UpdateProcedurePriceUseCase;
use App\Modules\CostCenter\Infrastructure\Http\Requests\StoreProcedurePriceRequest;
use App\Modules\CostCenter\Infrastructure\Http\Requests\UpdateProcedurePriceRequest;
use App\Modules\CostCenter\Infrastructure\Http\Resources\ProcedurePriceResource;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\ProcedurePriceModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group Tarifas de Procedimientos
 *
 * Gestión del histórico de precios/tarifas de cada procedimiento médico.
 * Solo aplica a registros de tipo "procedure" en medical_services.
 */
class ProcedurePriceController extends Controller
{
    use ApiResponse;

    /**
     * Listar tarifas de un procedimiento.
     *
     * @urlParam procedure integer required ID del procedimiento. Example: 2
     * @queryParam is_active boolean Filtrar por estado. Example: true
     * @queryParam effective_from string Fecha mínima de vigencia (Y-m-d). Example: 2026-01-01
     *
     * @response 200 {"success": true, "data": [{"id":1,"unit_price":50000,"effective_from":"2026-01-01",...}]}
     * @response 409 {"success": false, "message": "Procedimiento con id 99 no encontrado."}
     */
    public function index(Request $request, int $procedure, ListProcedurePricesUseCase $useCase): JsonResponse
    {
        $filters = $request->only(['is_active', 'effective_from']);
        $items   = $useCase->execute($procedure, $filters);

        return $this->success(
            ProcedurePriceResource::collection($items),
            'Tarifas del procedimiento',
        );
    }

    /**
     * Obtener una tarifa.
     *
     * @urlParam procedure integer required ID del procedimiento. Example: 2
     * @urlParam price integer required ID de la tarifa. Example: 1
     *
     * @response 200 {"success": true, "data": {"id":1,"unit_price":50000,...}}
     * @response 404 {"success": false, "message": "Tarifa no encontrada."}
     */
    public function show(int $procedure, int $price): JsonResponse
    {
        $model = ProcedurePriceModel::where('medical_service_id', $procedure)->find($price);

        if ($model === null) {
            return $this->error('Tarifa no encontrada', 404);
        }

        return $this->success(new ProcedurePriceResource($model), 'Detalle de la tarifa');
    }

    /**
     * Crear una tarifa para un procedimiento.
     *
     * @urlParam procedure integer required ID del procedimiento. Example: 2
     * @bodyParam unit_price number required Precio unitario (>= 0). Example: 50000
     * @bodyParam effective_from string required Fecha de inicio de vigencia (Y-m-d). Example: 2026-01-01
     * @bodyParam effective_to string Fecha de fin de vigencia (Y-m-d, opcional). Example: 2026-12-31
     * @bodyParam is_active boolean Estado (default: true). Example: true
     * @bodyParam notes string Notas opcionales. Example: Tarifa con IVA incluido
     *
     * @response 201 {"success": true, "data": {"id":1,"unit_price":50000,...}}
     * @response 409 {"success": false, "message": "El registro ... es un servicio, no un procedimiento."}
     * @response 422 {"success": false, "errors": {...}}
     */
    public function store(StoreProcedurePriceRequest $request, int $procedure, CreateProcedurePriceUseCase $useCase): JsonResponse
    {
        $validated = $request->validated();

        $price = $useCase->execute(new ProcedurePriceData(
            medicalServiceId: $procedure,
            unitPrice:        (float) $validated['unit_price'],
            effectiveFrom:    new DateTimeImmutable($validated['effective_from']),
            effectiveTo:      isset($validated['effective_to']) ? new DateTimeImmutable($validated['effective_to']) : null,
            isActive:         (bool) ($validated['is_active'] ?? true),
            notes:            $validated['notes'] ?? null,
        ));

        return $this->created(new ProcedurePriceResource($price), 'Tarifa creada exitosamente');
    }

    /**
     * Actualizar una tarifa.
     *
     * @urlParam procedure integer required ID del procedimiento. Example: 2
     * @urlParam price integer required ID de la tarifa. Example: 1
     *
     * @response 200 {"success": true, "data": {...}}
     * @response 409 {"success": false, "message": "Tarifa con id 99 no encontrada."}
     */
    public function update(UpdateProcedurePriceRequest $request, int $procedure, int $price, UpdateProcedurePriceUseCase $useCase): JsonResponse
    {
        $validated = $request->validated();

        $updated = $useCase->execute($price, new ProcedurePriceData(
            medicalServiceId: $procedure,
            unitPrice:        (float) $validated['unit_price'],
            effectiveFrom:    new DateTimeImmutable($validated['effective_from']),
            effectiveTo:      isset($validated['effective_to']) ? new DateTimeImmutable($validated['effective_to']) : null,
            isActive:         (bool) ($validated['is_active'] ?? true),
            notes:            $validated['notes'] ?? null,
        ));

        return $this->success(new ProcedurePriceResource($updated), 'Tarifa actualizada exitosamente');
    }

    /**
     * Eliminar una tarifa (soft delete).
     *
     * @urlParam procedure integer required ID del procedimiento. Example: 2
     * @urlParam price integer required ID de la tarifa. Example: 1
     *
     * @response 200 {"success": true, "message": "Tarifa eliminada"}
     * @response 409 {"success": false, "message": "Tarifa con id 99 no encontrada."}
     */
    public function destroy(int $procedure, int $price, DeleteProcedurePriceUseCase $useCase): JsonResponse
    {
        $useCase->execute($price);

        return $this->noContent('Tarifa eliminada');
    }
}
