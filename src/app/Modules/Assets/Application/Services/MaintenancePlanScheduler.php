<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\Services;

use App\Modules\Assets\Domain\Enums\MaintenanceStatus;
use App\Modules\Assets\Domain\Services\MaintenanceScheduleCalculator;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenanceModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenancePlanModel;
use DateTimeImmutable;

/**
 * Mantiene el cronograma generado por los planes: cada plan activo tiene
 * como máximo una orden abierta; al cerrarla o cancelarla se genera la
 * siguiente ocurrencia.
 */
class MaintenancePlanScheduler
{
    public function __construct(
        private readonly MaintenanceScheduleCalculator $calculator,
    ) {}

    /** Crea la siguiente orden del plan a partir de una fecha base (ejecución o fecha programada). */
    public function scheduleNext(AssetMaintenancePlanModel $plan, DateTimeImmutable $base, int $userId): ?AssetMaintenanceModel
    {
        if (! $plan->is_active || $plan->trashed()) {
            return null;
        }

        return $this->createOrder($plan, $this->calculator->next($base, $plan->frequency_value, $plan->frequency_unit), $userId);
    }

    /**
     * Ajusta la orden abierta tras crear o editar un plan: la crea si falta,
     * reprograma la que está pendiente o la cancela si el plan se desactivó.
     */
    public function sync(AssetMaintenancePlanModel $plan, int $userId): void
    {
        $open = AssetMaintenanceModel::where('plan_id', $plan->id)
            ->whereIn('status', [MaintenanceStatus::Scheduled->value, MaintenanceStatus::InProgress->value])
            ->first();

        if (! $plan->is_active || $plan->trashed()) {
            if ($open?->status === MaintenanceStatus::Scheduled) {
                $open->forceFill([
                    'status'        => MaintenanceStatus::Cancelled,
                    'cancelled_by'  => $userId,
                    'cancel_reason' => 'Plan de mantenimiento desactivado o eliminado.',
                ])->save();
            }

            return;
        }

        $date = $this->firstDate($plan);

        if ($open === null) {
            $this->createOrder($plan, $date, $userId);

            return;
        }

        if ($open->status === MaintenanceStatus::Scheduled) {
            $open->forceFill([
                'scheduled_date'    => $date->format('Y-m-d'),
                'maintenance_type'  => $plan->maintenance_type,
                'performed_by_type' => $plan->performed_by_type,
                'technician_id'     => $plan->technician_id,
                'supplier_id'       => $plan->supplier_id,
            ])->save();
        }
    }

    private function firstDate(AssetMaintenancePlanModel $plan): DateTimeImmutable
    {
        return $this->calculator->firstFrom(
            DateTimeImmutable::createFromInterface($plan->start_date),
            $plan->frequency_value,
            $plan->frequency_unit,
            new DateTimeImmutable('today'),
        );
    }

    private function createOrder(AssetMaintenancePlanModel $plan, DateTimeImmutable $date, int $userId): AssetMaintenanceModel
    {
        return AssetMaintenanceModel::create([
            'asset_id'          => $plan->asset_id,
            'plan_id'           => $plan->id,
            'maintenance_type'  => $plan->maintenance_type,
            'status'            => MaintenanceStatus::Scheduled,
            'scheduled_date'    => $date->format('Y-m-d'),
            'performed_by_type' => $plan->performed_by_type,
            'technician_id'     => $plan->technician_id,
            'supplier_id'       => $plan->supplier_id,
            'created_by'        => $userId,
        ]);
    }
}
