<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Domain\Enums\MaintenanceStatus;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenanceModel;

/** Cambia la fecha de una orden aún no iniciada. */
class RescheduleMaintenanceUseCase
{
    public function execute(int $maintenanceId, string $scheduledDate, ?string $reportedFailure = null): AssetMaintenanceModel
    {
        $maintenance = AssetMaintenanceModel::findOrFail($maintenanceId);

        if ($maintenance->status !== MaintenanceStatus::Scheduled) {
            throw new \DomainException('Solo se pueden reprogramar órdenes en estado programado.');
        }

        $maintenance->forceFill(array_filter([
            'scheduled_date'   => $scheduledDate,
            'reported_failure' => $reportedFailure,
        ], fn ($v) => $v !== null))->save();

        return $maintenance;
    }
}
