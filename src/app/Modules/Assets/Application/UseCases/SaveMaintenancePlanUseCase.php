<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Application\DTOs\MaintenancePlanData;
use App\Modules\Assets\Application\Services\MaintenancePlanScheduler;
use App\Modules\Assets\Domain\Enums\FrequencyUnit;
use App\Modules\Assets\Domain\Enums\MaintenanceType;
use App\Modules\Assets\Domain\Enums\PerformedByType;
use App\Modules\Assets\Domain\Repositories\AssetRepositoryInterface;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenancePlanModel;
use Illuminate\Support\Facades\DB;

/**
 * Crea (planId null) o actualiza un plan de mantenimiento periódico y
 * sincroniza su orden abierta en el cronograma.
 */
class SaveMaintenancePlanUseCase
{
    public function __construct(
        private readonly AssetRepositoryInterface $assets,
        private readonly MaintenancePlanScheduler $scheduler,
    ) {}

    public function execute(int $assetId, ?int $planId, MaintenancePlanData $data, int $userId): AssetMaintenancePlanModel
    {
        $asset = $this->assets->findById($assetId)
            ?? throw new \DomainException("Activo con id {$assetId} no encontrado.");

        $asset->ensureSupportsMaintenance();

        $type = MaintenanceType::from($data->maintenanceType);

        if (! in_array($type, MaintenanceType::plannable(), true)) {
            throw new \DomainException('El mantenimiento correctivo no se planifica: regístrelo como orden puntual.');
        }

        $performedBy = PerformedByType::from($data->performedByType);

        return DB::transaction(function () use ($assetId, $planId, $data, $type, $performedBy, $userId) {
            $plan = $planId !== null
                ? AssetMaintenancePlanModel::where('asset_id', $assetId)->findOrFail($planId)
                : new AssetMaintenancePlanModel(['asset_id' => $assetId, 'created_by' => $userId]);

            $plan->fill([
                'maintenance_type'  => $type,
                'frequency_value'   => $data->frequencyValue,
                'frequency_unit'    => FrequencyUnit::from($data->frequencyUnit),
                'start_date'        => $data->startDate,
                'performed_by_type' => $performedBy,
                'technician_id'     => $performedBy === PerformedByType::Internal ? $data->technicianId : null,
                'supplier_id'       => $performedBy === PerformedByType::External ? $data->supplierId : null,
                'notes'             => $data->notes,
                'is_active'         => $data->isActive,
            ])->save();

            $this->scheduler->sync($plan, $userId);

            return $plan->refresh();
        });
    }
}
