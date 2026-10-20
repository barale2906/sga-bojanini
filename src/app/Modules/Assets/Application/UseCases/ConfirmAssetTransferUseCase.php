<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Application\Services\AssetSignatureRecorder;
use App\Modules\Assets\Application\Services\AssetTransferApplier;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetSignatureModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetTransferModel;
use App\Modules\Auth\Application\DTOs\SignerInput;
use Illuminate\Support\Facades\DB;

/**
 * Confirma con firmas (entrega + recibe) un traslado pendiente y aplica
 * sus efectos sobre el activo.
 */
class ConfirmAssetTransferUseCase
{
    public function __construct(
        private readonly AssetTransferApplier $applier,
        private readonly AssetSignatureRecorder $signatures,
    ) {}

    /**
     * @param array{delivered_by: SignerInput, received_by: SignerInput} $signers
     */
    public function execute(int $transferId, array $signers, int $userId): AssetTransferModel
    {
        $transfer = AssetTransferModel::findOrFail($transferId);

        if (! $transfer->isPending()) {
            throw new \DomainException('El traslado no está pendiente de firma.');
        }

        $rows = $this->signatures->resolve($signers, $userId);

        return DB::transaction(function () use ($transfer, $rows, $userId) {
            $locked = AssetTransferModel::lockForUpdate()->findOrFail($transfer->id);

            if (! $locked->isPending()) {
                throw new \DomainException('El traslado no está pendiente de firma.');
            }

            $this->signatures->store(AssetSignatureModel::SIGNABLE_TRANSFER, $locked->id, $rows);
            $this->applier->apply($locked, $userId);

            return $locked->refresh();
        });
    }
}
