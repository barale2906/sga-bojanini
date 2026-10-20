<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Application\DTOs\AssetTransferRequestData;
use App\Modules\Assets\Application\Services\AssetSignatureRecorder;
use App\Modules\Assets\Application\Services\AssetTransferApplier;
use App\Modules\Assets\Domain\Entities\AssetPlace;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\AssetTransferStatus;
use App\Modules\Assets\Domain\Enums\AssetTransferType;
use App\Modules\Assets\Domain\Repositories\AssetPlaceRepositoryInterface;
use App\Modules\Assets\Domain\Repositories\AssetRepositoryInterface;
use App\Modules\Assets\Domain\Services\AssetTransferPolicy;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetSignatureModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetTransferModel;
use App\Modules\Auth\Application\DTOs\SignerInput;
use Illuminate\Support\Facades\DB;

/**
 * Registra un traslado de un activo.
 *
 * - Si el activo sale del edificio (otra sede, reparación externa, préstamo
 *   o baja) la firma es obligatoria: sin firmas queda `pending_signature` y
 *   no se aplica hasta confirmarse; con firmas se aplica de inmediato.
 * - Los traslados internos y retornos se aplican de inmediato; si llegan
 *   firmas se guardan (opcional).
 */
class RequestAssetTransferUseCase
{
    public function __construct(
        private readonly AssetRepositoryInterface $assets,
        private readonly AssetPlaceRepositoryInterface $places,
        private readonly AssetTransferPolicy $policy,
        private readonly AssetTransferApplier $applier,
        private readonly AssetSignatureRecorder $signatures,
    ) {}

    /**
     * @param array{delivered_by: SignerInput, received_by: SignerInput}|null $signers
     *
     * @throws \DomainException
     */
    public function execute(int $assetId, AssetTransferRequestData $data, ?array $signers, int $userId): AssetTransferModel
    {
        $asset = $this->assets->findById($assetId)
            ?? throw new \DomainException("Activo con id {$assetId} no encontrado.");

        $model       = AssetModel::with('currentPlace')->findOrFail($assetId);
        $targetPlace = $this->resolveTargetPlace($data);

        $type = $this->policy->resolveType(
            asset:               $asset,
            requested:           AssetTransferType::from($data->type),
            hasPendingTransfer:  $model->pendingTransfer()->exists(),
            targetPlaceId:       $targetPlace?->getId() ?? $asset->getCurrentPlaceId(),
            currentSiteId:       $model->currentPlace?->site_id,
            targetSiteId:        $targetPlace?->getSiteId(),
            targetResponsibleId: $data->toResponsibleId,
        );

        $this->validateResultingStatus($type, $data->resultingStatus);

        // Las firmas se resuelven antes de la transacción: si fallan no se toca nada.
        $signatureRows = $signers !== null ? $this->signatures->resolve($signers, $userId) : null;

        return DB::transaction(function () use ($model, $type, $data, $targetPlace, $signatureRows, $userId) {
            $transfer = AssetTransferModel::create([
                'asset_id'               => $model->id,
                'type'                   => $type,
                'status'                 => AssetTransferStatus::PendingSignature,
                'requires_signature'     => $type->requiresSignature(),
                'from_place_id'          => $model->current_place_id,
                'to_place_id'            => $targetPlace?->getId(),
                'from_external_location' => $model->is_off_premises ? $model->off_premises_location : null,
                'external_destination'   => $type->leavesPremises() ? $data->externalDestination : null,
                'supplier_id'            => $data->supplierId,
                'from_responsible_id'    => $model->responsible_id,
                'to_responsible_id'      => $data->toResponsibleId,
                'expected_return_date'   => $data->expectedReturnDate,
                'resulting_status'       => $data->resultingStatus,
                'reason'                 => $data->reason,
                'requested_by'           => $userId,
            ]);

            if ($signatureRows !== null) {
                $this->signatures->store(AssetSignatureModel::SIGNABLE_TRANSFER, $transfer->id, $signatureRows);
            }

            if (! $type->requiresSignature() || $signatureRows !== null) {
                $this->applier->apply($transfer, $userId);
            }

            return $transfer->refresh();
        });
    }

    private function resolveTargetPlace(AssetTransferRequestData $data): ?AssetPlace
    {
        if ($data->toPlaceId === null) {
            if ($data->type === AssetTransferType::Return->value) {
                throw new \DomainException('Indique el sitio al que retorna el activo.');
            }

            return null;
        }

        $place = $this->places->findById($data->toPlaceId);

        if ($place === null || ! $place->isActive()) {
            throw new \DomainException('El sitio destino no existe o está inactivo.');
        }

        return $place;
    }

    private function validateResultingStatus(AssetTransferType $type, ?string $status): void
    {
        if ($status === null) {
            return;
        }

        if ($type !== AssetTransferType::Return) {
            throw new \DomainException('El estado resultante solo aplica al retorno del activo.');
        }

        if (! in_array(AssetStatus::tryFrom($status), AssetStatus::manuallySettable(), true)) {
            throw new \DomainException('Estado resultante no válido.');
        }
    }
}
