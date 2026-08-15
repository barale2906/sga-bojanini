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
use App\Modules\Inventory\Infrastructure\Http\Resources\MovementDocumentResource;
use App\Modules\Inventory\Infrastructure\Http\Resources\MovementResource;
use App\Modules\Inventory\Infrastructure\Import\InitialEntriesImport;
use App\Modules\Inventory\Infrastructure\Persistence\Models\MovementDocumentModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use App\Modules\Shared\Infrastructure\Http\Traits\ChecksWarehouseAccess;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

        $document = $useCase->execute([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->created(new MovementDocumentResource($document), 'Entrada registrada exitosamente');
    }

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

        $document = $useCase->execute([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->created(new MovementDocumentResource($document), 'Salida registrada exitosamente');
    }

    public function transfer(StoreTransferRequest $request, TransferStockUseCase $useCase): JsonResponse
    {
        $this->assertWarehouseAccess($request->user(), (int) $request->validated('warehouse_from_id'));
        $this->assertWarehouseAccess($request->user(), (int) $request->validated('warehouse_to_id'));

        $document = $useCase->execute([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->created(new MovementDocumentResource($document), 'Traslado registrado exitosamente');
    }

    public function adjustment(StoreAdjustmentRequest $request, AdjustStockUseCase $useCase): JsonResponse
    {
        $this->assertWarehouseAccess($request->user(), (int) $request->validated('warehouse_id'));

        $movement = $useCase->execute([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->created(
            new MovementResource($movement->load(['variant.genericProduct', 'batch', 'user', 'document'])),
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
            new MovementResource($movement->load(['variant.genericProduct', 'batch', 'user', 'document'])),
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
            new MovementResource($movement->load(['variant.genericProduct', 'batch', 'user', 'document'])),
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
            new MovementResource($movement->load(['variant.genericProduct', 'batch', 'user', 'document'])),
            'Baja de inventario registrada exitosamente',
        );
    }

    /** Lista movimientos individuales con filtros (para reportes detallados). */
    public function index(ListMovementsRequest $request): JsonResponse
    {
        $query = StockMovementModel::with(['variant.genericProduct.category', 'batch', 'user', 'document.costCenter', 'document.medicalService']);

        if ($request->filled('date_from')) {
            $query->whereDate('movement_date', '>=', Carbon::parse($request->date_from)->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('movement_date', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        if ($request->filled('warehouse_id')) {
            $this->assertWarehouseAccess($request->user(), $request->integer('warehouse_id'));
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('generic_product_id')) {
            $query->whereHas('variant', fn ($q) => $q->where('generic_product_id', $request->integer('generic_product_id')));
        } elseif ($request->filled('product_variant_id')) {
            $query->where('product_variant_id', $request->integer('product_variant_id'));
        }

        if ($request->filled('category_id')) {
            $query->whereHas('variant.genericProduct', fn ($q) => $q->where('category_id', $request->integer('category_id')));
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

    /** Lista documentos de movimiento con filtros (para comprobantes y reimpresión). */
    public function indexDocuments(Request $request): JsonResponse
    {
        $query = MovementDocumentModel::with(['warehouse', 'warehouseTo', 'user', 'costCenter', 'medicalService', 'signatures']);

        if ($request->filled('date_from')) {
            $query->whereDate('movement_date', '>=', Carbon::parse($request->input('date_from'))->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('movement_date', '<=', Carbon::parse($request->input('date_to'))->endOfDay());
        }

        if ($request->filled('warehouse_id')) {
            $this->assertWarehouseAccess($request->user(), (int) $request->input('warehouse_id'));
            $query->where('warehouse_id', (int) $request->input('warehouse_id'));
        }

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->input('document_type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('cost_center_id')) {
            $query->where('cost_center_id', (int) $request->input('cost_center_id'));
        }

        if ($request->filled('document_number')) {
            $query->where('document_number', 'like', '%' . $request->input('document_number') . '%');
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
            $query->orderByDesc('created_at')->paginate($perPage)->through(fn ($doc) => new MovementDocumentResource($doc)),
            'Listado de documentos de movimiento',
        );
    }

    /** Devuelve el comprobante completo con todas sus líneas. */
    public function showDocument(int $id, Request $request): JsonResponse
    {
        $document = MovementDocumentModel::with([
            'movements.variant.genericProduct',
            'movements.batch',
            'warehouse',
            'warehouseTo',
            'user',
            'costCenter',
            'medicalService',
            'signatures',
        ])->find($id);

        if ($document === null) {
            return $this->error('Documento no encontrado', 404);
        }

        $this->assertDocumentAccess($request, $document);

        return $this->success(new MovementDocumentResource($document), 'Detalle del comprobante');
    }

    public function cancelPendingDocument(int $id, Request $request): JsonResponse
    {
        $document = MovementDocumentModel::with('movements')->findOrFail($id);

        if ($document->status->value !== 'pending_signature') {
            return $this->error('Solo se pueden cancelar documentos pendientes de firma.', 422);
        }

        $this->assertDocumentAccess($request, $document);

        $document->delete();

        return $this->success(null, 'Documento pendiente cancelado exitosamente');
    }

    public function confirm(int $id, ConfirmMovementRequest $request, ConfirmMovementUseCase $useCase): JsonResponse
    {
        $document = MovementDocumentModel::findOrFail($id);
        $this->assertDocumentAccess($request, $document);

        $document = $useCase->execute($id, $request->signatures());

        return $this->success(new MovementDocumentResource($document), 'Documento confirmado exitosamente');
    }

    public function showSignature(int $id, string $role, Request $request): JsonResponse
    {
        $document = MovementDocumentModel::findOrFail($id);
        $this->assertDocumentAccess($request, $document);

        $signature = $document->signatures()->where('role', $role)->first();

        if ($signature === null) {
            return $this->error('Firma no encontrada', 404);
        }

        return $this->success([
            'role'            => $signature->role,
            'signer_name'     => $signature->signer_name,
            'signer_document' => $signature->signer_document,
            'signature_data'  => $signature->signature_data,
            'signed_at'       => $signature->signed_at?->toIso8601String(),
        ], 'Firma del documento');
    }

    /** Descarga el PDF del comprobante completo con todas sus líneas y firmas. */
    public function downloadDocumentPdf(int $id, Request $request): Response
    {
        $document = MovementDocumentModel::with([
            'movements.variant.genericProduct',
            'movements.batch',
            'movements.locationFrom',
            'movements.locationTo',
            'warehouse',
            'warehouseTo',
            'user',
            'costCenter',
            'medicalService',
            'signatures',
        ])->findOrFail($id);

        $this->assertDocumentAccess($request, $document);

        $pdf = Pdf::loadView('reports.movement_voucher', ['document' => $document])
            ->setPaper('letter', 'portrait');

        $filename = 'comprobante-' . $document->document_number . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /** Endpoint de detalle de movimiento individual (para reportes de línea). */
    public function show(int $id, Request $request): JsonResponse
    {
        $movement = StockMovementModel::with(['variant.genericProduct', 'batch', 'user', 'document.costCenter', 'document.medicalService', 'document.signatures'])->find($id);

        if ($movement === null) {
            return $this->error('Movimiento no encontrado', 404);
        }

        $this->assertMovementAccess($request, $movement);

        return $this->success(new MovementResource($movement), 'Detalle del movimiento');
    }

    private function assertDocumentAccess(Request $request, MovementDocumentModel $document): void
    {
        $user = $request->user();
        $hasFullAccess = $this->userHasFullWarehouseAccess($user);

        $canAccessSource = $this->warehouseAccessService()->canAccessWarehouse(
            (int) $user->getAuthIdentifier(),
            $document->warehouse_id,
            $hasFullAccess,
        );

        $canAccessDestination = $document->warehouse_to_id !== null && $this->warehouseAccessService()->canAccessWarehouse(
            (int) $user->getAuthIdentifier(),
            $document->warehouse_to_id,
            $hasFullAccess,
        );

        if (! $canAccessSource && ! $canAccessDestination) {
            throw new AuthorizationException('No tienes acceso al almacén indicado.');
        }
    }

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
