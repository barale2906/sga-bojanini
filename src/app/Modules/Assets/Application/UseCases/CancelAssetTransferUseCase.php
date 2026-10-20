<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Domain\Enums\AssetTransferStatus;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetTransferModel;

class CancelAssetTransferUseCase
{
    public function execute(int $transferId, ?string $reason, int $userId): AssetTransferModel
    {
        $transfer = AssetTransferModel::findOrFail($transferId);

        if (! $transfer->isPending()) {
            throw new \DomainException('Solo se pueden cancelar traslados pendientes de firma.');
        }

        $transfer->forceFill([
            'status'        => AssetTransferStatus::Cancelled,
            'cancelled_by'  => $userId,
            'cancel_reason' => $reason,
        ])->save();

        return $transfer;
    }
}
