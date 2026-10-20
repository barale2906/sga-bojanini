<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Services;

use App\Modules\Assets\Domain\Enums\AssetMovementType;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\AssetTransferStatus;
use App\Modules\Assets\Domain\Enums\AssetTransferType;
use App\Modules\Assets\Domain\Enums\MaintenanceStatus;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenanceModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenancePlanModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetTransferModel;

/**
 * Aplica los efectos de un traslado sobre el activo: ubicación,
 * responsable, estado y bitácora. Se usa al solicitar un traslado que no
 * requiere firma y al confirmar uno que sí la requería.
 */
class AssetTransferApplier
{
    public function __construct(
        private readonly AssetCustodyService $custody,
        private readonly AssetMovementRecorder $movements,
    ) {}

    public function apply(AssetTransferModel $transfer, int $userId): void
    {
        /** @var AssetModel $asset */
        $asset      = AssetModel::lockForUpdate()->findOrFail($transfer->asset_id);
        $fromStatus = $asset->status;

        match ($transfer->type) {
            AssetTransferType::Relocation,
            AssetTransferType::SiteTransfer   => $this->relocate($asset, $transfer, $userId),
            AssetTransferType::ExternalRepair => $this->sendOut($asset, $transfer, $userId, AssetStatus::Maintenance),
            AssetTransferType::Loan           => $this->sendOut($asset, $transfer, $userId),
            AssetTransferType::Return         => $this->returnToPremises($asset, $transfer, $userId),
            AssetTransferType::Retirement     => $this->retire($asset, $transfer, $userId),
        };

        $transfer->forceFill([
            'status'       => AssetTransferStatus::Confirmed,
            'confirmed_at' => now(),
        ])->save();

        $toStatus = $asset->refresh()->status;

        $this->movements->record(
            assetId:     $asset->id,
            type:        $transfer->type === AssetTransferType::Retirement ? AssetMovementType::Retirement : AssetMovementType::Transfer,
            userId:      $userId,
            description: $this->describe($transfer),
            fromStatus:  $fromStatus,
            toStatus:    $toStatus !== $fromStatus ? $toStatus : null,
            transferId:  $transfer->id,
        );
    }

    private function relocate(AssetModel $asset, AssetTransferModel $transfer, int $userId): void
    {
        if ($transfer->to_place_id !== null && $transfer->to_place_id !== $asset->current_place_id) {
            $this->custody->moveToPlace($asset, $transfer->to_place_id, $userId, $transfer->id);
        }

        if ($transfer->to_responsible_id !== null) {
            $this->custody->assignResponsible($asset, $transfer->to_responsible_id);
        }
    }

    private function sendOut(AssetModel $asset, AssetTransferModel $transfer, int $userId, ?AssetStatus $status = null): void
    {
        $this->custody->moveOffPremises($asset, (string) $transfer->external_destination, $userId, $transfer->id);

        if ($status !== null) {
            $asset->forceFill(['status' => $status])->save();
        }
    }

    private function returnToPremises(AssetModel $asset, AssetTransferModel $transfer, int $userId): void
    {
        $this->custody->moveToPlace($asset, (int) $transfer->to_place_id, $userId, $transfer->id);

        if ($transfer->to_responsible_id !== null) {
            $this->custody->assignResponsible($asset, $transfer->to_responsible_id);
        }

        if ($transfer->resulting_status !== null) {
            $asset->forceFill(['status' => AssetStatus::from($transfer->resulting_status)])->save();
        }
    }

    /** Baja: el activo sale de inventario, sus planes se desactivan y las órdenes abiertas se cancelan. */
    private function retire(AssetModel $asset, AssetTransferModel $transfer, int $userId): void
    {
        $this->custody->moveOffPremises($asset, $transfer->external_destination ?: 'Dado de baja', $userId, $transfer->id);

        $asset->forceFill([
            'status'            => AssetStatus::Retired,
            'retired_at'        => now()->toDateString(),
            'retirement_reason' => $transfer->reason,
        ])->save();

        AssetMaintenancePlanModel::where('asset_id', $asset->id)->update(['is_active' => false]);

        AssetMaintenanceModel::where('asset_id', $asset->id)
            ->whereIn('status', [MaintenanceStatus::Scheduled->value, MaintenanceStatus::InProgress->value])
            ->update([
                'status'        => MaintenanceStatus::Cancelled->value,
                'cancelled_by'  => $userId,
                'cancel_reason' => 'Activo dado de baja.',
            ]);
    }

    private function describe(AssetTransferModel $transfer): string
    {
        $transfer->loadMissing(['fromPlace.site', 'toPlace.site', 'toResponsible']);

        $from = $transfer->fromPlace?->fullName() ?? $transfer->from_external_location ?? '—';
        $to   = $transfer->toPlace?->fullName() ?? $transfer->external_destination ?? '—';

        $text = "{$transfer->type->label()}: {$from} → {$to}";

        if ($transfer->toResponsible !== null) {
            $text .= ". Responsable: {$transfer->toResponsible->name}";
        }

        return $transfer->reason ? "{$text}. Motivo: {$transfer->reason}" : $text;
    }
}
