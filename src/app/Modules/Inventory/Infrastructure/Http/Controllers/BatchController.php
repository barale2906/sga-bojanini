<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Controllers;

use App\Modules\Inventory\Infrastructure\Http\Resources\BatchResource;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Shared\Infrastructure\Http\Traits\ChecksWarehouseAccess;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BatchController extends Controller
{
    use ApiResponse;
    use ChecksWarehouseAccess;

    public function index(Request $request): JsonResponse
    {
        $query = BatchModel::with('variant.genericProduct');

        if ($request->filled('product_variant_id')) {
            $query->where('product_variant_id', $request->integer('product_variant_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('warehouse_id')) {
            $warehouseId = $request->integer('warehouse_id');
            $this->assertWarehouseAccess($request->user(), $warehouseId);
            $query->whereHas('locations.zone', fn ($q) => $q->where('warehouse_id', $warehouseId));
        } else {
            $this->scopeBatchesToAllowedWarehouses($query, $request);
        }

        $perPage = min(
            (int) $request->query('per_page', config('sga.pagination.default_per_page', 25)),
            config('sga.pagination.max_per_page', 100),
        );

        return $this->paginated(
            $query->orderBy('expiration_date')->paginate($perPage)->through(fn ($batch) => new BatchResource($batch)),
            'Listado de lotes',
        );
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $batch = BatchModel::with(['variant.genericProduct', 'locations.zone'])->find($id);

        if ($batch === null) {
            return $this->error('Lote no encontrado', 404);
        }

        $this->assertBatchWarehouseAccess($batch, $request);

        return $this->success(new BatchResource($batch), 'Detalle del lote');
    }

    /**
     * Lista los lotes de un producto.
     *
     * Si se envía `available_for_exit=1`, solo se devuelven los lotes
     * vigentes (status = 'active', expiration_date >= hoy) con cantidad
     * disponible mayor a cero, útil para los selectores de lote de los
     * formularios de salida y transferencia.
     *
     * Si se envía `warehouse_id`, solo se devuelven los lotes que tienen
     * stock en alguna ubicación de ese almacén, útil para el selector de
     * lote del formulario de baja (`movements/loss`).
     */
    public function byProduct(int $id, Request $request): JsonResponse
    {
        $query = BatchModel::with(['variant.genericProduct', 'locations.zone'])
            ->where('product_variant_id', $id);

        if ($request->boolean('available_for_exit')) {
            $query->availableForExit();
        }

        if ($request->filled('warehouse_id')) {
            $warehouseId = $request->integer('warehouse_id');
            $this->assertWarehouseAccess($request->user(), $warehouseId);
            $query->whereHas('locations.zone', fn ($q) => $q->where('warehouse_id', $warehouseId));
        } else {
            $this->scopeBatchesToAllowedWarehouses($query, $request);
        }

        $batches = $query->orderBy('expiration_date')->get();

        return $this->success(BatchResource::collection($batches), 'Lotes del producto');
    }

    /**
     * Lista los lotes de un producto genérico (todos sus variantes) ordenados FEFO.
     * Acepta los mismos filtros que byProduct: available_for_exit y warehouse_id.
     */
    public function byGenericProduct(int $genericId, Request $request): JsonResponse
    {
        $query = BatchModel::with(['variant.genericProduct', 'locations.zone'])
            ->whereHas('variant', fn ($q) => $q->where('generic_product_id', $genericId));

        if ($request->boolean('available_for_exit')) {
            $query->availableForExit();
        }

        if ($request->filled('warehouse_id')) {
            $warehouseId = $request->integer('warehouse_id');
            $this->assertWarehouseAccess($request->user(), $warehouseId);
            $query->whereHas('locations.zone', fn ($q) => $q->where('warehouse_id', $warehouseId));
        } else {
            $this->scopeBatchesToAllowedWarehouses($query, $request);
        }

        $batches = $query->orderBy('expiration_date')->get();

        return $this->success(BatchResource::collection($batches), 'Lotes del producto genérico');
    }

    public function expiring(Request $request): JsonResponse
    {
        $alertDays = (int) $request->query('days', config('sga.fefo.alert_days', 30));
        $alertDate = Carbon::today()->addDays($alertDays);

        $query = BatchModel::with('variant.genericProduct')
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->whereDate('expiration_date', '<=', $alertDate)
            ->whereDate('expiration_date', '>=', Carbon::today());

        $this->scopeBatchesToAllowedWarehouses($query, $request);

        $batches = $query->orderBy('expiration_date')->get();

        return $this->success(BatchResource::collection($batches), 'Lotes próximos a vencer');
    }

    public function expired(Request $request): JsonResponse
    {
        $query = BatchModel::with('variant.genericProduct')->where('status', 'expired');

        $this->scopeBatchesToAllowedWarehouses($query, $request);

        $batches = $query->orderByDesc('expiration_date')->get();

        return $this->success(BatchResource::collection($batches), 'Lotes vencidos');
    }

    /** Restringe el listado a lotes con stock en alguno de los almacenes permitidos. */
    private function scopeBatchesToAllowedWarehouses(Builder $query, Request $request): void
    {
        $allowedIds = $this->allowedWarehouseIds($request->user());

        if ($allowedIds !== null) {
            $query->whereHas('locations.zone', fn ($q) => $q->whereIn('warehouse_id', $allowedIds));
        }
    }

    /** Verifica que el lote tenga stock en al menos un almacén permitido para el usuario. */
    private function assertBatchWarehouseAccess(BatchModel $batch, Request $request): void
    {
        $allowedIds = $this->allowedWarehouseIds($request->user());

        if ($allowedIds === null) {
            return;
        }

        $batchWarehouseIds = $batch->locations->pluck('zone.warehouse_id')->unique();

        if ($batchWarehouseIds->isNotEmpty() && $batchWarehouseIds->intersect($allowedIds)->isEmpty()) {
            throw new AuthorizationException('No tienes acceso al almacén indicado.');
        }
    }
}
