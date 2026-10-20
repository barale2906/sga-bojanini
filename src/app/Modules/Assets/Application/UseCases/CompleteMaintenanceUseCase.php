<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Application\DTOs\CompleteMaintenanceData;
use App\Modules\Assets\Application\Services\AssetMovementRecorder;
use App\Modules\Assets\Application\Services\AssetSignatureRecorder;
use App\Modules\Assets\Application\Services\MaintenancePlanScheduler;
use App\Modules\Assets\Domain\Enums\AssetMovementType;
use App\Modules\Assets\Domain\Enums\MaintenanceResult;
use App\Modules\Assets\Domain\Enums\MaintenanceStatus;
use App\Modules\Assets\Domain\Enums\PerformedByType;
use App\Modules\Assets\Domain\Repositories\AssetRepositoryInterface;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenanceModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetSignatureModel;
use App\Modules\Auth\Application\DTOs\SignerInput;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Registra la ejecución de un mantenimiento.
 *
 * Obligatorio: fecha de ejecución, actividades, resultado y quién lo
 * ejecutó (técnico interno, o proveedor + nombre del técnico externo).
 * Quien registra se toma de la sesión. Soportes y firmas son opcionales.
 * Si la orden proviene de un plan, se programa la siguiente ocurrencia.
 */
class CompleteMaintenanceUseCase
{
    public function __construct(
        private readonly AssetRepositoryInterface $assets,
        private readonly AssetMovementRecorder $movements,
        private readonly AssetSignatureRecorder $signatures,
        private readonly MaintenancePlanScheduler $scheduler,
    ) {}

    /**
     * @param array{delivered_by: SignerInput, received_by: SignerInput}|null $signers
     */
    public function execute(int $maintenanceId, CompleteMaintenanceData $data, ?array $signers, int $userId): AssetMaintenanceModel
    {
        $maintenance = AssetMaintenanceModel::with('plan')->findOrFail($maintenanceId);

        if (! $maintenance->status->isOpen()) {
            throw new \DomainException('La orden ya fue registrada o cancelada.');
        }

        $asset = $this->assets->findById($maintenance->asset_id)
            ?? throw new \DomainException('El activo de la orden no existe.');

        $asset->ensureSupportsMaintenance();

        $performedBy = PerformedByType::from($data->performedByType);
        $this->ensurePerformerIdentified($performedBy, $data);

        $result      = MaintenanceResult::from($data->result);
        $performedAt = new DateTimeImmutable($data->performedAt);

        if ($performedAt > new DateTimeImmutable('now')) {
            throw new \DomainException('La fecha de ejecución no puede ser futura.');
        }

        $signatureRows = $signers !== null ? $this->signatures->resolve($signers, $userId) : null;

        return DB::transaction(function () use ($maintenance, $asset, $data, $performedBy, $result, $performedAt, $signatureRows, $userId) {
            $maintenance->forceFill([
                'status'                   => MaintenanceStatus::Completed,
                'performed_at'             => $performedAt,
                'performed_by_type'        => $performedBy,
                'technician_id'            => $performedBy === PerformedByType::Internal ? $data->technicianId : null,
                'supplier_id'              => $performedBy === PerformedByType::External ? $data->supplierId : null,
                'external_technician_name' => $performedBy === PerformedByType::External ? $data->externalTechnicianName : null,
                'activities_performed'     => $data->activitiesPerformed,
                'findings'                 => $data->findings,
                'recommendations'          => $data->recommendations,
                'parts_replaced'           => $data->partsReplaced ?: null,
                'cost'                     => $data->cost,
                'result'                   => $result,
                'registered_by'            => $userId,
            ])->save();

            $newStatus = $result->resultingAssetStatus();

            if ($newStatus !== $asset->getStatus()) {
                $this->assets->updateStatus($asset->getId(), $newStatus);
            }

            if ($signatureRows !== null) {
                $this->signatures->store(AssetSignatureModel::SIGNABLE_MAINTENANCE, $maintenance->id, $signatureRows);
            }

            $this->movements->record(
                assetId:       $asset->getId(),
                type:          AssetMovementType::Maintenance,
                userId:        $userId,
                description:   "Mantenimiento {$maintenance->maintenance_type->label()} realizado. Resultado: {$result->label()}.",
                fromStatus:    $asset->getStatus(),
                toStatus:      $newStatus !== $asset->getStatus() ? $newStatus : null,
                maintenanceId: $maintenance->id,
            );

            if ($maintenance->plan !== null) {
                $this->scheduler->scheduleNext($maintenance->plan, $performedAt, $userId);
            }

            return $maintenance->refresh();
        });
    }

    private function ensurePerformerIdentified(PerformedByType $type, CompleteMaintenanceData $data): void
    {
        if ($type === PerformedByType::Internal && $data->technicianId === null) {
            throw new \DomainException('Indique el técnico interno que realizó el mantenimiento.');
        }

        if ($type === PerformedByType::External && ($data->supplierId === null || blank($data->externalTechnicianName))) {
            throw new \DomainException('Indique el proveedor y el nombre del técnico externo que realizó el mantenimiento.');
        }
    }
}
