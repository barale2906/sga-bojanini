<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Controllers;

use App\Modules\Inventory\Infrastructure\Http\Resources\BatchResource;
use App\Modules\Inventory\Infrastructure\Persistence\Models\BatchModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class BatchController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = BatchModel::with('product');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('warehouse_id')) {
            $warehouseId = $request->integer('warehouse_id');
            $query->whereHas('locations.zone', fn ($q) => $q->where('warehouse_id', $warehouseId));
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

    public function show(int $id): JsonResponse
    {
        $batch = BatchModel::with(['product', 'locations.zone'])->find($id);

        if ($batch === null) {
            return $this->error('Lote no encontrado', 404);
        }

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
        $query = BatchModel::with(['product', 'locations.zone'])
            ->where('product_id', $id);

        if ($request->boolean('available_for_exit')) {
            $query->availableForExit();
        }

        if ($request->filled('warehouse_id')) {
            $warehouseId = $request->integer('warehouse_id');
            $query->whereHas('locations.zone', fn ($q) => $q->where('warehouse_id', $warehouseId));
        }

        $batches = $query->orderBy('expiration_date')->get();

        return $this->success(BatchResource::collection($batches), 'Lotes del producto');
    }

    public function expiring(Request $request): JsonResponse
    {
        $alertDays = (int) $request->query('days', config('sga.fefo.alert_days', 30));
        $alertDate = Carbon::today()->addDays($alertDays);

        $batches = BatchModel::with('product')
            ->where('status', 'active')
            ->where('quantity_available', '>', 0)
            ->whereDate('expiration_date', '<=', $alertDate)
            ->whereDate('expiration_date', '>=', Carbon::today())
            ->orderBy('expiration_date')
            ->get();

        return $this->success(BatchResource::collection($batches), 'Lotes próximos a vencer');
    }

    public function expired(): JsonResponse
    {
        $batches = BatchModel::with('product')
            ->where('status', 'expired')
            ->orderByDesc('expiration_date')
            ->get();

        return $this->success(BatchResource::collection($batches), 'Lotes vencidos');
    }
}
