<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\ExternalServices;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MedsysAppointmentService
{
    /** Estados considerados "activos" para el flujo de atención */
    private const ACTIVE_STATES = ['PEN', 'CNF', 'CON'];

    /** @return Collection<int, object> */
    public function getActiveAppointments(string $patientCode, ?string $date = null): Collection
    {
        return DB::connection('medsys')
            ->table('controles as c')
            ->join('tiposproc as t', 'c.codtipocontrol', '=', 't.codigo')
            ->join('estadoscita as e', 'c.estado', '=', 'e.codigo')
            ->where('c.idpaciente', $patientCode)
            ->whereIn('c.estado', self::ACTIVE_STATES)
            ->when($date, fn ($q) => $q->whereDate('c.fecha', $date))
            ->select(
                'c.codcontrol',
                'c.fecha',
                'c.hora',
                'c.codtipocontrol',
                't.descripcion as servicio',
                'e.descripcion as estado',
            )
            ->orderBy('c.fecha')
            ->orderBy('c.hora')
            ->get();
    }

    /**
     * Últimas 3 citas del paciente (cualquier estado), de más reciente a más antigua.
     *
     * @return Collection<int, object>
     */
    public function getPatientSummary(string $patientCode): Collection
    {
        return DB::connection('medsys')
            ->table('controles as c')
            ->join('tiposproc as t', 'c.codtipocontrol', '=', 't.codigo')
            ->join('estadoscita as e', 'c.estado', '=', 'e.codigo')
            ->where('c.idpaciente', $patientCode)
            ->select(
                'c.codcontrol',
                'c.fecha',
                'c.hora',
                'c.codtipocontrol',
                't.descripcion as servicio',
                'e.descripcion as estado',
            )
            ->orderBy('c.fecha', 'desc')
            ->orderBy('c.hora', 'desc')
            ->limit(3)
            ->get();
    }

    /**
     * Agrupa las citas futuras por tipo de procedimiento.
     * Usado por ConsumptionProjectionService para calcular proyecciones.
     *
     * @return Collection<int, object>  [{codtipocontrol, servicio, total_citas}]
     */
    public function getFutureAppointmentsGrouped(int $days = 30): Collection
    {
        return DB::connection('medsys')
            ->table('controles as c')
            ->join('tiposproc as t', 'c.codtipocontrol', '=', 't.codigo')
            ->whereIn('c.estado', ['PEN', 'CNF'])
            ->whereBetween('c.fecha', [now()->toDateString(), now()->addDays($days)->toDateString()])
            ->groupBy('c.codtipocontrol', 't.descripcion')
            ->select(
                'c.codtipocontrol',
                't.descripcion as servicio',
                DB::raw('COUNT(*) as total_citas'),
            )
            ->get();
    }
}
