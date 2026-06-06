<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Controllers;

use App\Modules\Inventory\Application\UseCases\AdjustStockUseCase;
use App\Modules\Inventory\Application\UseCases\RegisterEntryUseCase;
use App\Modules\Inventory\Application\UseCases\RegisterExitUseCase;
use App\Modules\Inventory\Application\UseCases\RegisterReturnUseCase;
use App\Modules\Inventory\Application\UseCases\TransferStockUseCase;
use App\Modules\Inventory\Application\UseCases\WriteOffExpiredUseCase;
use Carbon\Carbon;
use App\Modules\Inventory\Infrastructure\Http\Requests\ListMovementsRequest;
use App\Modules\Inventory\Infrastructure\Http\Requests\StoreAdjustmentRequest;
use App\Modules\Inventory\Infrastructure\Http\Requests\StoreEntryRequest;
use App\Modules\Inventory\Infrastructure\Http\Requests\StoreExitRequest;
use App\Modules\Inventory\Infrastructure\Http\Requests\StoreReturnRequest;
use App\Modules\Inventory\Infrastructure\Http\Requests\StoreTransferRequest;
use App\Modules\Inventory\Infrastructure\Http\Requests\StoreWriteOffRequest;
use App\Modules\Inventory\Infrastructure\Http\Resources\MovementResource;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class MovementController extends Controller
{
    use ApiResponse;

    public function entry(StoreEntryRequest $request, RegisterEntryUseCase $useCase): JsonResponse
    {
        $movement = $useCase->execute([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->created(
            new MovementResource($movement->load(['product', 'batch', 'user'])),
            'Entrada registrada exitosamente',
        );
    }

    public function exit(StoreExitRequest $request, RegisterExitUseCase $useCase): JsonResponse
    {
        $result = $useCase->execute([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        if (is_array($result)) {
            $result['movements']->each->load(['product', 'batch', 'user']);

            return $this->created([
                'kit_transaction_id' => $result['kit_transaction']->id,
                'movements'          => MovementResource::collection($result['movements']),
            ], 'Salida de kit registrada exitosamente');
        }

        return $this->created(
            new MovementResource($result->load(['product', 'batch', 'user', 'costCenter', 'medicalService'])),
            'Salida registrada exitosamente',
        );
    }

    public function transfer(StoreTransferRequest $request, TransferStockUseCase $useCase): JsonResponse
    {
        $movement = $useCase->execute([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->created(
            new MovementResource($movement->load(['product', 'batch', 'user', 'warehouse', 'warehouseTo'])),
            'Traslado registrado exitosamente',
        );
    }

    public function adjustment(StoreAdjustmentRequest $request, AdjustStockUseCase $useCase): JsonResponse
    {
        $movement = $useCase->execute([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->created(
            new MovementResource($movement->load(['product', 'batch', 'user'])),
            'Ajuste registrado exitosamente',
        );
    }

    public function returnStock(StoreReturnRequest $request, RegisterReturnUseCase $useCase): JsonResponse
    {
        $movement = $useCase->execute([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->created(
            new MovementResource($movement->load(['product', 'batch', 'user'])),
            'Devolución registrada exitosamente',
        );
    }

    public function writeOff(StoreWriteOffRequest $request, WriteOffExpiredUseCase $useCase): JsonResponse
    {
        $movement = $useCase->execute([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->created(
            new MovementResource($movement->load(['product', 'batch', 'user'])),
            'Baja por vencimiento registrada exitosamente',
        );
    }

    public function index(ListMovementsRequest $request): JsonResponse
    {

        $query = StockMovementModel::with(['product', 'batch', 'user', 'costCenter', 'medicalService']);

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->string('movement_type'));
        }

        if ($request->filled('cost_center_id')) {
            $query->where('cost_center_id', $request->integer('cost_center_id'));
        }

        if ($request->filled('cost_center_type')) {
            $query->whereHas('costCenter', fn ($q) => $q->where('type', $request->string('cost_center_type')));
        }

        if ($request->filled('warehouse_to_id')) {
            $query->where('warehouse_to_id', $request->integer('warehouse_to_id'));
        }

        $perPage = min(
            (int) $request->query('per_page', config('sga.pagination.default_per_page', 25)),
            config('sga.pagination.max_per_page', 100),
        );

        return $this->paginated(
            $query->orderByDesc('created_at')->paginate($perPage)->through(fn ($movement) => new MovementResource($movement)),
            'Listado de movimientos',
        );
    }

    public function show(int $id): JsonResponse
    {
        $movement = StockMovementModel::with(['product', 'batch', 'user', 'costCenter', 'medicalService'])->find($id);

        if ($movement === null) {
            return $this->error('Movimiento no encontrado', 404);
        }

        return $this->success(new MovementResource($movement), 'Detalle del movimiento');
    }
}
