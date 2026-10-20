<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Application\Services\AssetMovementRecorder;
use App\Modules\Assets\Application\Services\MaintenancePlanScheduler;
use App\Modules\Assets\Domain\Enums\AssetMovementType;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\MaintenanceStatus;
use App\Modules\Assets\Domain\Repositories\AssetRepositoryInterface;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenanceModel;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Cancela una orden abierta. Si estaba en ejecución, el activo vuelve a
 * operativo; si venía de un plan, se programa la siguiente ocurrencia.
 */
class CancelMaintenanceUseCase
{
    public function __construct(
        private readonly AssetRepositoryInterface $assets,
        private readonly AssetMovementRecorder $movements,
        private readonly MaintenancePlanScheduler $scheduler,
    ) {}

    public function execute(int $maintenanceId, string $reason, int $userId): AssetMaintenanceModel
    {
        $maintenance = AssetMaintenanceModel::with('plan')->findOrFail($maintenanceId);

        if (! $maintenance->status->isOpen()) {
            throw new \DomainException('Solo se pueden cancelar órdenes programadas o en ejecución.');
        }

        return DB::transaction(function () use ($maintenance, $reason, $userId) {
            $wasInProgress = $maintenance->status === MaintenanceStatus::InProgress;

            $maintenance->forceFill([
                'status'        => MaintenanceStatus::Cancelled,
                'cancelled_by'  => $userId,
                'cancel_reason' => $reason,
            ])->save();

            $asset = $this->assets->findById($maintenance->asset_id);

            if ($wasInProgress && $asset !== null && $asset->getStatus() === AssetStatus::Maintenance && ! $asset->isOffPremises()) {
                $this->assets->updateStatus($asset->getId(), AssetStatus::Operational);

                $this->movements->record(
                    assetId:       $asset->getId(),
                    type:          AssetMovementType::Maintenance,
                    userId:        $userId,
                    description:   "Mantenimiento cancelado: {$reason}",
                    fromStatus:    AssetStatus::Maintenance,
                    toStatus:      AssetStatus::Operational,
                    maintenanceId: $maintenance->id,
                );
            }

            if ($maintenance->plan !== null) {
                $this->scheduler->scheduleNext(
                    $maintenance->plan,
                    DateTimeImmutable::createFromInterface($maintenance->scheduled_date),
                    $userId,
                );
            }

            return $maintenance->refresh();
        });
    }
}
