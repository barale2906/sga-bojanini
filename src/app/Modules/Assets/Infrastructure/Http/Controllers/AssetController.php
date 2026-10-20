<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Controllers;

use App\Modules\Assets\Application\UseCases\AddAssetCommentUseCase;
use App\Modules\Assets\Application\UseCases\ChangeAssetStatusUseCase;
use App\Modules\Assets\Application\UseCases\CreateAssetUseCase;
use App\Modules\Assets\Application\UseCases\DeleteAssetUseCase;
use App\Modules\Assets\Application\UseCases\UpdateAssetUseCase;
use App\Modules\Assets\Domain\Enums\AssetDocumentType;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\AssetTransferType;
use App\Modules\Assets\Domain\Enums\AssetType;
use App\Modules\Assets\Domain\Enums\MaintenanceResult;
use App\Modules\Assets\Domain\Enums\MaintenanceStatus;
use App\Modules\Assets\Domain\Enums\MaintenanceType;
use App\Modules\Assets\Infrastructure\Http\Requests\ChangeAssetStatusRequest;
use App\Modules\Assets\Infrastructure\Http\Requests\StoreAssetCommentRequest;
use App\Modules\Assets\Infrastructure\Http\Requests\StoreAssetRequest;
use App\Modules\Assets\Infrastructure\Http\Requests\UpdateAssetRequest;
use App\Modules\Assets\Infrastructure\Http\Resources\AssetCommentResource;
use App\Modules\Assets\Infrastructure\Http\Resources\AssetLocationHistoryResource;
use App\Modules\Assets\Infrastructure\Http\Resources\AssetMovementResource;
use App\Modules\Assets\Infrastructure\Http\Resources\AssetResource;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetCategoryModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetCommentModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetLocationHistoryModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMovementModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetSiteModel;
use App\Modules\Assets\Infrastructure\Queries\AssetQuery;
use App\Modules\Auth\Infrastructure\Persistence\Models\UserModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group Activos
 *
 * Inventario de activos: equipos biomédicos, de cómputo y bienes generales.
 */
class AssetController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AssetQuery $query,
    ) {}

    /**
     * Listar activos (paginado).
     *
     * @queryParam search string Código, nombre, serial, marca o modelo. Example: monitor
     * @queryParam category_id integer Categoría. Example: 1
     * @queryParam asset_type string biomedical | computing | general. Example: biomedical
     * @queryParam site_id integer Sede. Example: 1
     * @queryParam place_id integer Sitio. Example: 3
     * @queryParam status string operational | maintenance | out_of_service | available | retired. Example: operational
     * @queryParam responsible_id integer Responsable. Example: 5
     * @queryParam is_off_premises boolean Fuera de las instalaciones. Example: false
     * @queryParam include_retired boolean Incluir dados de baja (por defecto se excluyen). Example: false
     * @queryParam warranty_expiring_days integer Garantía que vence en los próximos N días. Example: 30
     * @queryParam maintenance_overdue boolean Con mantenimiento vencido. Example: true
     * @queryParam sort_by string code | name | status | acquisition_date | warranty_until | created_at. Example: code
     * @queryParam sort_dir string asc | desc. Example: asc
     * @queryParam per_page integer Máx. 100. Example: 15
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginated(
            $this->query->paginate($request->all(), (int) $request->input('per_page', 15))
                ->through(fn ($asset) => new AssetResource($asset)),
            'Listado de activos',
        );
    }

    /**
     * Catálogos para filtros y formularios: categorías, sedes/sitios, usuarios
     * activos (responsables y técnicos), proveedores y enumeraciones.
     */
    public function filters(): JsonResponse
    {
        $options = fn (array $cases) => array_map(fn ($c) => ['value' => $c->value, 'label' => $c->label()], $cases);

        return $this->success([
            'categories'          => AssetCategoryModel::where('is_active', true)->orderBy('name')
                ->get(['id', 'code', 'name', 'asset_type'])
                ->map(fn ($c) => [
                    'id'                   => $c->id,
                    'code'                 => $c->code,
                    'name'                 => $c->name,
                    'asset_type'           => $c->asset_type->value,
                    'requires_maintenance' => $c->asset_type->requiresMaintenance(),
                ]),
            'sites'               => AssetSiteModel::where('is_active', true)->orderBy('name')
                ->with(['places' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
                ->get()
                ->map(fn ($s) => [
                    'id'     => $s->id,
                    'name'   => $s->name,
                    'places' => $s->places->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
                ]),
            'users'               => UserModel::where('is_active', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name]),
            'suppliers'           => SupplierModel::where('is_active', true)->orderBy('name')->get(['id', 'name', 'tax_id'])
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'tax_id' => $s->tax_id]),
            'asset_types'         => $options(AssetType::cases()),
            'statuses'            => $options(AssetStatus::cases()),
            'settable_statuses'   => $options(AssetStatus::manuallySettable()),
            'transfer_types'      => array_map(fn ($t) => [
                'value'              => $t->value,
                'label'              => $t->label(),
                'requires_signature' => $t->requiresSignature(),
            ], AssetTransferType::requestable()),
            'maintenance_types'   => $options(MaintenanceType::cases()),
            'plannable_types'     => $options(MaintenanceType::plannable()),
            'maintenance_status'  => $options(MaintenanceStatus::cases()),
            'maintenance_results' => $options(MaintenanceResult::cases()),
            'document_types'      => $options(AssetDocumentType::cases()),
        ], 'Catálogos de activos');
    }

    /** Indicadores (por estado, tipo, sede, valor, mantenimientos y traslados pendientes). */
    public function statistics(Request $request): JsonResponse
    {
        return $this->success($this->query->statistics($request->all()), 'Estadísticas de activos');
    }

    /** Ficha del activo (incluye hoja de vida y contadores). */
    public function show(int $id): JsonResponse
    {
        $asset = $this->query->find($id);

        return $asset
            ? $this->success(new AssetResource($asset), 'Detalle del activo')
            : $this->error('Activo no encontrado', 404);
    }

    /**
     * Registrar activo. El código se genera automáticamente (000001…).
     *
     * @response 201 {"success": true, "data": {"id": 1, "code": "000001", "...": "..."}}
     */
    public function store(StoreAssetRequest $request, CreateAssetUseCase $useCase): JsonResponse
    {
        $asset = $useCase->execute($request->toData(), (int) $request->user()->id);

        return $this->created(new AssetResource($this->query->find($asset->getId())), 'Activo registrado');
    }

    /** Actualizar datos generales. */
    public function update(UpdateAssetRequest $request, int $id, UpdateAssetUseCase $useCase): JsonResponse
    {
        $useCase->execute($id, $request->toData(), (int) $request->user()->id);

        return $this->success(new AssetResource($this->query->find($id)), 'Activo actualizado');
    }

    /** Eliminar un activo registrado por error (si tiene historia, debe darse de baja). */
    public function destroy(int $id, DeleteAssetUseCase $useCase): JsonResponse
    {
        $useCase->execute($id);

        return $this->noContent('Activo eliminado');
    }

    /** Cambiar estado (operativo, en mantenimiento, fuera de servicio, disponible). */
    public function changeStatus(ChangeAssetStatusRequest $request, int $id, ChangeAssetStatusUseCase $useCase): JsonResponse
    {
        $useCase->execute($id, AssetStatus::from($request->validated('status')), $request->validated('reason'), (int) $request->user()->id);

        return $this->success(new AssetResource($this->query->find($id)), 'Estado actualizado');
    }

    /** Bitácora de eventos del activo. */
    public function movements(Request $request, int $id): JsonResponse
    {
        AssetModel::withTrashed()->findOrFail($id);

        return $this->paginated(
            AssetMovementModel::with('user')->where('asset_id', $id)
                ->orderByDesc('created_at')->orderByDesc('id')
                ->paginate(min((int) $request->input('per_page', 20), 100))
                ->through(fn ($movement) => new AssetMovementResource($movement)),
            'Bitácora del activo',
        );
    }

    /** Historial de ubicaciones (más reciente primero). */
    public function locationHistory(int $id): JsonResponse
    {
        AssetModel::withTrashed()->findOrFail($id);

        $history = AssetLocationHistoryModel::with(['place.site', 'transfer', 'assignedBy'])
            ->where('asset_id', $id)
            ->orderByDesc('from_date')->orderByDesc('id')
            ->get();

        return $this->success(AssetLocationHistoryResource::collection($history), 'Historial de ubicaciones');
    }

    /** Comentarios acumulados del activo (más reciente primero). */
    public function comments(int $id): JsonResponse
    {
        AssetModel::withTrashed()->findOrFail($id);

        $comments = AssetCommentModel::with('user')->where('asset_id', $id)
            ->orderByDesc('created_at')->orderByDesc('id')->get();

        return $this->success(AssetCommentResource::collection($comments), 'Comentarios del activo');
    }

    /** Agregar comentario (no se edita ni elimina). */
    public function storeComment(StoreAssetCommentRequest $request, int $id, AddAssetCommentUseCase $useCase): JsonResponse
    {
        $comment = $useCase->execute($id, $request->validated('comment'), (int) $request->user()->id);

        return $this->created(new AssetCommentResource($comment), 'Comentario agregado');
    }
}
