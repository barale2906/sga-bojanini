<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Controllers;

use App\Modules\Assets\Application\UseCases\CancelAssetTransferUseCase;
use App\Modules\Assets\Application\UseCases\ConfirmAssetTransferUseCase;
use App\Modules\Assets\Application\UseCases\RequestAssetTransferUseCase;
use App\Modules\Assets\Domain\Enums\AssetTransferType;
use App\Modules\Assets\Infrastructure\Http\Requests\CancelWithReasonRequest;
use App\Modules\Assets\Infrastructure\Http\Requests\ConfirmAssetTransferRequest;
use App\Modules\Assets\Infrastructure\Http\Requests\RequestAssetTransferRequest;
use App\Modules\Assets\Infrastructure\Http\Resources\AssetTransferResource;
use App\Modules\Assets\Infrastructure\Pdf\AssetPdfRenderer;
use App\Modules\Assets\Infrastructure\Queries\AssetTransferQuery;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * @group Activos — Traslados
 *
 * Traslados internos, entre sedes, salidas a reparación externa, préstamos,
 * retornos y bajas. La firma (entrega con sesión + recibe con PIN o pad) es
 * obligatoria solo cuando el activo sale del edificio.
 */
class AssetTransferController extends Controller
{
    use ApiResponse;

    public const RETIREMENT_PERMISSION = 'activos.dar_baja';

    public function __construct(
        private readonly AssetTransferQuery $query,
    ) {}

    /**
     * Listar traslados.
     *
     * @queryParam status string pending_signature | confirmed | cancelled. Example: pending_signature
     * @queryParam type string Tipo de traslado. Example: external_repair
     * @queryParam asset_id integer Activo. Example: 1
     * @queryParam search string Código o nombre del activo. Example: 000012
     */
    public function index(Request $request): JsonResponse
    {
        return $this->paginated(
            $this->query->paginate($request->all(), (int) $request->input('per_page', 15))
                ->through(fn ($transfer) => new AssetTransferResource($transfer)),
            'Traslados de activos',
        );
    }

    public function show(int $id): JsonResponse
    {
        $transfer = $this->query->find($id);

        return $transfer
            ? $this->success(new AssetTransferResource($transfer), 'Detalle del traslado')
            : $this->error('Traslado no encontrado', 404);
    }

    /**
     * Registrar un traslado.
     *
     * Sin firmas, los tipos que sacan el activo del edificio quedan en
     * `pending_signature`; los internos y retornos se aplican de inmediato.
     * Si se envían `received_by` (y opcional `delivered_by`) se firma y aplica en un paso.
     *
     * @bodyParam type string required relocation | external_repair | loan | return | retirement. Example: relocation
     * @bodyParam to_place_id integer Sitio destino (obligatorio en return). Example: 4
     * @bodyParam to_responsible_id integer Nuevo responsable. Example: 7
     * @bodyParam external_destination string Destino externo (obligatorio en external_repair y loan). Example: Taller BioService
     * @bodyParam reason string Motivo (obligatorio en retirement). Example: Equipo obsoleto
     *
     * @response 201 {"success": true, "data": {"id": 3, "type": "site_transfer", "status": "pending_signature"}}
     * @response 409 {"success": false, "message": "El activo 000012 tiene un traslado pendiente de firma. Confírmelo o cancélelo primero."}
     */
    public function store(RequestAssetTransferRequest $request, int $assetId, RequestAssetTransferUseCase $useCase): JsonResponse
    {
        if ($request->validated('type') === AssetTransferType::Retirement->value
            && ! $request->user()->can(self::RETIREMENT_PERMISSION)) {
            return $this->error('No tiene permiso para dar de baja activos.', 403);
        }

        $transfer = $useCase->execute($assetId, $request->toData(), $request->signers(), (int) $request->user()->id);

        $message = $transfer->isPending()
            ? 'Traslado registrado: pendiente de firma de entrega y recibido'
            : 'Traslado aplicado';

        return $this->created(new AssetTransferResource($this->query->find($transfer->id)), $message);
    }

    /** Confirmar con firmas un traslado pendiente. */
    public function confirm(ConfirmAssetTransferRequest $request, int $id, ConfirmAssetTransferUseCase $useCase): JsonResponse
    {
        $transfer = $useCase->execute($id, $request->signers(), (int) $request->user()->id);

        return $this->success(new AssetTransferResource($this->query->find($transfer->id)), 'Traslado confirmado');
    }

    public function cancel(CancelWithReasonRequest $request, int $id, CancelAssetTransferUseCase $useCase): JsonResponse
    {
        $transfer = $useCase->execute($id, $request->validated('reason'), (int) $request->user()->id);

        return $this->success(new AssetTransferResource($this->query->find($transfer->id)), 'Traslado cancelado');
    }

    /** Acta del traslado en PDF (con firmas si fue firmado). */
    public function act(int $id, AssetPdfRenderer $renderer): Response
    {
        $transfer = $this->query->find($id) ?? abort(404);

        return $renderer->transferAct($transfer);
    }
}
