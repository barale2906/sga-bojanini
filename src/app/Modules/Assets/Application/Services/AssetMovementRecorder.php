<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Services;

use App\Modules\Assets\Domain\Enums\AssetMovementType;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMovementModel;

/** Escribe la bitácora (solo lectura) de eventos de un activo. */
class AssetMovementRecorder
{
    public function record(
        int $assetId,
        AssetMovementType $type,
        int $userId,
        ?string $description = null,
        ?AssetStatus $fromStatus = null,
        ?AssetStatus $toStatus = null,
        ?int $transferId = null,
        ?int $maintenanceId = null,
    ): void {
        AssetMovementModel::create([
            'asset_id'       => $assetId,
            'type'           => $type,
            'from_status'    => $fromStatus?->value,
            'to_status'      => $toStatus?->value,
            'transfer_id'    => $transferId,
            'maintenance_id' => $maintenanceId,
            'description'    => $description,
            'user_id'        => $userId,
        ]);
    }
}
