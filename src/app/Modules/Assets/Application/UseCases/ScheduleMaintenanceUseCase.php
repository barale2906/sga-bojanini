<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Application\DTOs\ScheduleMaintenanceData;
use App\Modules\Assets\Domain\Enums\MaintenanceStatus;
use App\Modules\Assets\Domain\Enums\MaintenanceType;
use App\Modules\Assets\Domain\Enums\PerformedByType;
use App\Modules\Assets\Domain\Repositories\AssetRepositoryInterface;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenanceModel;

/** Programa una orden de mantenimiento puntual (correctivo o no planificado). */
class ScheduleMaintenanceUseCase
{
    public function __construct(
        private readonly AssetRepositoryInterface $assets,
    ) {}

    public function execute(int $assetId, ScheduleMaintenanceData $data, int $userId): AssetMaintenanceModel
    {
        $asset = $this->assets->findById($assetId)
            ?? throw new \DomainException("Activo con id {$assetId} no encontrado.");

        $asset->ensureSupportsMaintenance();

        $type = MaintenanceType::from($data->maintenanceType);

        if ($type === MaintenanceType::Corrective && blank($data->reportedFailure)) {
            throw new \DomainException('Describa la falla reportada para el mantenimiento correctivo.');
        }

        $performedBy = $data->performedByType !== null ? PerformedByType::from($data->performedByType) : null;

        return AssetMaintenanceModel::create([
            'asset_id'          => $assetId,
            'maintenance_type'  => $type,
            'status'            => MaintenanceStatus::Scheduled,
            'scheduled_date'    => $data->scheduledDate,
            'reported_failure'  => $data->reportedFailure,
            'performed_by_type' => $performedBy,
            'technician_id'     => $performedBy === PerformedByType::Internal ? $data->technicianId : null,
            'supplier_id'       => $performedBy === PerformedByType::External ? $data->supplierId : null,
            'created_by'        => $userId,
        ]);
    }
}
