<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Application\Services\AssetMovementRecorder;
use App\Modules\Assets\Domain\Enums\AssetMovementType;
use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\MaintenanceStatus;
use App\Modules\Assets\Domain\Repositories\AssetRepositoryInterface;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenanceModel;
use Illuminate\Support\Facades\DB;

/** Inicia la ejecución de una orden: el activo pasa a "En mantenimiento". */
class StartMaintenanceUseCase
{
    public function __construct(
        private readonly AssetRepositoryInterface $assets,
        private readonly AssetMovementRecorder $movements,
    ) {}

    public function execute(int $maintenanceId, int $userId): AssetMaintenanceModel
    {
        $maintenance = AssetMaintenanceModel::findOrFail($maintenanceId);

        if ($maintenance->status !== MaintenanceStatus::Scheduled) {
            throw new \DomainException('Solo se pueden iniciar órdenes en estado programado.');
        }

        $asset = $this->assets->findById($maintenance->asset_id)
            ?? throw new \DomainException('El activo de la orden no existe.');

        $asset->ensureSupportsMaintenance();

        return DB::transaction(function () use ($maintenance, $asset, $userId) {
            $maintenance->forceFill([
                'status'     => MaintenanceStatus::InProgress,
                'started_at' => now(),
            ])->save();

            if ($asset->getStatus() !== AssetStatus::Maintenance) {
                $this->assets->updateStatus($asset->getId(), AssetStatus::Maintenance);
            }

            $this->movements->record(
                assetId:       $asset->getId(),
                type:          AssetMovementType::Maintenance,
                userId:        $userId,
                description:   "Inicio de mantenimiento {$maintenance->maintenance_type->label()}.",
                fromStatus:    $asset->getStatus(),
                toStatus:      AssetStatus::Maintenance,
                maintenanceId: $maintenance->id,
            );

            return $maintenance;
        });
    }
}
