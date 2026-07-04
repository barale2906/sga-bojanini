<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Infrastructure\Persistence\Models\ImportLogModel;
use App\Modules\Inventory\Application\Services\StorageAllocationService;
use App\Modules\Inventory\Application\UseCases\AdjustStockUseCase;
use App\Modules\Inventory\Application\UseCases\ConfirmMovementUseCase;
use App\Modules\Inventory\Application\UseCases\ImportInitialEntriesUseCase;
use App\Modules\Inventory\Application\UseCases\RegisterEntryUseCase;
use App\Modules\Inventory\Application\UseCases\RegisterExitUseCase;
use App\Modules\Inventory\Application\UseCases\RegisterLossUseCase;
use App\Modules\Inventory\Application\UseCases\RegisterReturnUseCase;
use App\Modules\Inventory\Application\UseCases\TransferStockUseCase;
use App\Modules\Inventory\Application\UseCases\WriteOffExpiredUseCase;
use App\Modules\Inventory\Infrastructure\Export\InitialEntryTemplateBuilder;
use Carbon\Carbon;
use App\Modules\Inventory\Infrastructure\Http\Requests\ConfirmMovementRequest;
use App\Modules\Inventory\Infrastructure\Http\Requests\ImportInitialEntriesRequest;
use App\Modules\Inventory\Infrastructure\Http\Requests\ListMovementsRequest;
use App\Modules\Inventory\Infrastructure\Http\Requests\StoreAdjustmentRequest;
use App\Modules\Inventory\Infrastructure\Http\Requests\StoreEntryRequest;
use App\Modules\Inventory\Infrastructure\Http\Requests\StoreExitRequest;
use App\Modules\Inventory\Infrastructure\Http\Requests\StoreLossRequest;
use App\Modules\Inventory\Infrastructure\Http\Requests\StoreReturnRequest;
use App\Modules\Inventory\Infrastructure\Http\Requests\StoreTransferRequest;
use App\Modules\Inventory\Infrastructure\Http\Requests\StoreWriteOffRequest;
use App\Modules\Inventory\Infrastructure\Http\Resources\MovementResource;
use App\Modules\Inventory\Infrastructure\Import\InitialEntriesImport;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Shared\Infrastructure\Http\Traits\ChecksWarehouseAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MovementController extends Controller
{
    use ApiResponse;
    use ChecksWarehouseAccess;

    public function entry(StoreEntryRequest $request, RegisterEntryUseCase $useCase): JsonResponse
    {
        $this->assertWarehouseAccess($request->user(), (int) $request->validated('warehouse_id'));

        $movement = $useCase->execute([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->created(
            new MovementResource($movement->load(['product', 'batch', 'user'])),
            'Entrada registrada exitosamente',
        );
    }

    /**
     * Carga masiva de inventario inicial vía Excel. El almacén se toma de
     * `warehouse_id` si se envía; si no, se usa el primer almacén activo
     * del sistema. La zona y el estante destino se resuelven siempre de
     * forma automática dentro de ese almacén (primera zona activa, o la
     * zona de refrigeración para productos con cadena de frío) — ver
     * `StorageAllocationService`.
     */
    public function importInitialEntries(
        ImportInitialEntriesRequest $request,
        StorageAllocationService $allocationService,
        ImportInitialEntriesUseCase $useCase,
    ): JsonResponse {
        $warehouse = $allocationService->resolveWarehouse($request->filled('warehouse_id') ? (int) $request->validated('warehouse_id') : null);

        $this->assertWarehouseAccess($request->user(), $warehouse->id);

        $import = new InitialEntriesImport();
        Excel::import($import, $request->file('file'));

        $results = $useCase->execute($import->getRows(), $warehouse->id, (int) $request->user()->id);

        ImportLogModel::create([
            'file_name'    => $request->file('file')->getClientOriginalName(),
            'entity_type'  => 'initial_entries',
            'total_rows'   => $results['total'],
            'success_rows' => $results['success'],
            'error_rows'   => $results['failed'],
            'errors'       => $results['errors'],
            'user_id'      => $request->user()->id,
        ]);

        return $this->success($results, 'Importación de entradas iniciales finalizada');
    }

    /**
     * Descarga la plantilla Excel de entradas iniciales. Acepta
     * `warehouse_id` opcional por query string solo para que la hoja
     * "Almacén y zona destino" muestre la zona de ese almacén en
     * particular; no afecta a la importación posterior (cada `POST` define
     * su propio `warehouse_id`).
     */
    public function downloadInitialEntriesTemplate(Request $request, InitialEntryTemplateBuilder $builder): BinaryFileResponse
    {
        $warehouseId = $request->filled('warehouse_id') ? $request->integer('warehouse_id') : null;

        $spreadsheet = $builder->build($warehouseId);

        $tempPath = tempnam(sys_get_temp_dir(), 'initial_entries_template_');
        (new Xlsx($spreadsheet))->save($tempPath);

        return response()->download($tempPath, 'initial_entries_template.xlsx')->deleteFileAfterSend(true);
    }

    public function exit(StoreExitRequest $request, RegisterExitUseCase $useCase): JsonResponse
    {
        $this->assertWarehouseAccess($request->user(), (int) $request->validated('warehouse_id'));

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

        $result->each->load(['product', 'batch', 'user', 'costCenter', 'medicalService']);

        return $this->created(
            MovementResource::collection($result),
            'Salida registrada exitosamente',
        );
    }

    public function transfer(StoreTransferRequest $request, TransferStockUseCase $useCase): JsonResponse
    {
        $this->assertWarehouseAccess($request->user(), (int) $request->validated('warehouse_from_id'));
        $this->assertWarehouseAccess($request->user(), (int) $request->validated('warehouse_to_id'));

        $movements = $useCase->execute([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        $movements->each->load(['product', 'batch', 'user', 'warehouse', 'warehouseTo']);

        return $this->created(
            MovementResource::collection($movements),
            'Traslado registrado exitosamente',
        );
    }

    public function adjustment(StoreAdjustmentRequest $request, AdjustStockUseCase $useCase): JsonResponse
    {
        $this->assertWarehouseAccess($request->user(), (int) $request->validated('warehouse_id'));

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
        $this->assertWarehouseAccess($request->user(), (int) $request->validated('warehouse_id'));

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
        $this->assertWarehouseAccess($request->user(), (int) $request->validated('warehouse_id'));

        $movement = $useCase->execute([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->created(
            new MovementResource($movement->load(['product', 'batch', 'user'])),
            'Baja por vencimiento registrada exitosamente',
        );
    }

    public function loss(StoreLossRequest $request, RegisterLossUseCase $useCase): JsonResponse
    {
        $this->assertWarehouseAccess($request->user(), (int) $request->validated('warehouse_id'));

        $movement = $useCase->execute([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->created(
            new MovementResource($movement->load(['product', 'batch', 'user'])),
            'Baja de inventario registrada exitosamente',
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
            $this->assertWarehouseAccess($request->user(), $request->integer('warehouse_id'));
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

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('warehouse_to_id')) {
            $this->assertWarehouseAccess($request->user(), $request->integer('warehouse_to_id'));
            $query->where('warehouse_to_id', $request->integer('warehouse_to_id'));
        }

        $allowedIds = $this->allowedWarehouseIds($request->user());

        if ($allowedIds !== null) {
            $query->where(function ($q) use ($allowedIds) {
                $q->whereIn('warehouse_id', $allowedIds)
                    ->orWhereIn('warehouse_to_id', $allowedIds);
            });
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

    public function cancelPending(int $id, Request $request): JsonResponse
    {
        $movement = StockMovementModel::findOrFail($id);

        if ($movement->status->value !== 'pending_signature') {
            return $this->error('Solo se pueden eliminar movimientos pendientes de firma.', 422);
        }

        $this->assertMovementAccess($request, $movement);

        $movement->delete();

        return $this->success(null, 'Movimiento pendiente eliminado exitosamente');
    }

    public function confirm(int $id, ConfirmMovementRequest $request, ConfirmMovementUseCase $useCase): JsonResponse
    {
        $movement = StockMovementModel::findOrFail($id);
        $this->assertMovementAccess($request, $movement);

        $movement = $useCase->execute($id, $request->signatures());

        return $this->success(
            new MovementResource($movement),
            'Movimiento confirmado exitosamente',
        );
    }

    public function showSignature(int $id, string $role, Request $request): JsonResponse
    {
        $movement = StockMovementModel::findOrFail($id);
        $this->assertMovementAccess($request, $movement);

        $signature = $movement->signatures()->where('role', $role)->first();

        if ($signature === null) {
            return $this->error('Firma no encontrada', 404);
        }

        return $this->success([
            'role'            => $signature->role,
            'signer_name'     => $signature->signer_name,
            'signer_document' => $signature->signer_document,
            'signature_data'  => $signature->signature_data,
            'signed_at'       => $signature->signed_at?->toIso8601String(),
        ], 'Firma del movimiento');
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $movement = StockMovementModel::with(['product', 'batch', 'user', 'costCenter', 'medicalService', 'signatures'])->find($id);

        if ($movement === null) {
            return $this->error('Movimiento no encontrado', 404);
        }

        $this->assertMovementAccess($request, $movement);

        return $this->success(new MovementResource($movement), 'Detalle del movimiento');
    }

    /** Concede acceso si el usuario puede ver el almacén de origen o el de destino. */
    private function assertMovementAccess(Request $request, StockMovementModel $movement): void
    {
        $user = $request->user();
        $hasFullAccess = $this->userHasFullWarehouseAccess($user);

        $canAccessSource = $this->warehouseAccessService()->canAccessWarehouse(
            (int) $user->getAuthIdentifier(),
            $movement->warehouse_id,
            $hasFullAccess,
        );

        $canAccessDestination = $movement->warehouse_to_id !== null && $this->warehouseAccessService()->canAccessWarehouse(
            (int) $user->getAuthIdentifier(),
            $movement->warehouse_to_id,
            $hasFullAccess,
        );

        if (! $canAccessSource && ! $canAccessDestination) {
            throw new AuthorizationException('No tienes acceso al almacén indicado.');
        }
    }
}
