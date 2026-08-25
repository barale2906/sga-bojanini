<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\ExternalServices;

use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\Inventory\Infrastructure\Persistence\Models\StockMovementModel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Proyecta la cantidad de insumos necesaria para las citas futuras,
 * basándose en el promedio histórico de salidas por servicio.
 *
 * proyección_insumo = citas_futuras × promedio_histórico_unidades_por_cita
 */
class ConsumptionProjectionService
{
    public function __construct(
        private readonly MedsysAppointmentService $appointmentService,
    ) {}

    /**
     * @param  int $days           Horizonte de proyección en días
     * @param  int $historicalDays Período histórico para calcular el promedio
     * @return Collection<int, array{service_id, service_name, external_code, future_appointments, avg_units_per_appointment, projected_units}>
     */
    public function project(int $days = 30, int $historicalDays = 90): Collection
    {
        $futureByType = $this->appointmentService
            ->getFutureAppointmentsGrouped($days)
            ->keyBy('codtipocontrol');

        if ($futureByType->isEmpty()) {
            return collect();
        }

        $services = MedicalServiceModel::whereIn(
            'external_code',
            $futureByType->keys()->all(),
        )->get();

        $historicalAvg = $this->buildHistoricalAverage($services->pluck('id')->all(), $historicalDays);

        return $services->map(function (MedicalServiceModel $svc) use ($futureByType, $historicalAvg) {
            $typeCode  = $svc->external_code;
            $futureCnt = (int) ($futureByType->get($typeCode)?->total_citas ?? 0);
            $avg       = (float) ($historicalAvg[$svc->id] ?? 0);

            return [
                'service_id'                  => $svc->id,
                'service_name'                => $svc->name,
                'external_code'               => $typeCode,
                'future_appointments'         => $futureCnt,
                'avg_units_per_appointment'   => round($avg, 2),
                'projected_units'             => (int) ceil($futureCnt * $avg),
            ];
        })->values();
    }

    /** @param int[] $serviceIds */
    private function buildHistoricalAverage(array $serviceIds, int $historicalDays): array
    {
        if (empty($serviceIds)) {
            return [];
        }

        $rows = DB::table('stock_movements as sm')
            ->join('patient_procedure_records as ppr', 'sm.movement_document_id', '=', 'ppr.id')
            ->whereIn('ppr.medical_service_id', $serviceIds)
            ->where('sm.type', 'salida')
            ->where('sm.created_at', '>=', now()->subDays($historicalDays))
            ->groupBy('ppr.medical_service_id')
            ->select('ppr.medical_service_id', DB::raw('AVG(sm.quantity) as avg_units'))
            ->get();

        return $rows->pluck('avg_units', 'medical_service_id')->all();
    }
}
