<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\Http\Controllers;

use App\Modules\CostCenter\Infrastructure\Persistence\Models\MedicalServiceModel;
use App\Modules\Integration\Infrastructure\ExternalServices\ConsumptionProjectionService;
use App\Modules\Integration\Infrastructure\ExternalServices\MedsysAppointmentService;
use App\Modules\Integration\Infrastructure\ExternalServices\MedsysPatientService;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group Integración MedSys — Pacientes y Citas
 *
 * Búsqueda de pacientes y citas en MedSys, mapeo de procedimientos
 * y proyección de consumos para órdenes de compra anticipadas.
 */
class MedsysPatientController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly MedsysPatientService     $patientService,
        private readonly MedsysAppointmentService $appointmentService,
    ) {}

    /**
     * Buscar paciente en MedSys por un término único.
     *
     * El backend detecta automáticamente el tipo de búsqueda:
     * - Solo dígitos → búsqueda exacta por documento; devuelve un paciente + citas activas de hoy.
     * - Letras / mixto → búsqueda parcial por nombre; devuelve lista de pacientes sin citas.
     *
     * @queryParam search string required Documento (numérico) o nombre (mín. 3 caracteres). Example: García
     *
     * @response 200 {"success":true,"data":{"patient":{...},"appointments":[...]}}
     * @response 200 {"success":true,"data":{"patients":[...]}}
     * @response 404 {"success":false,"message":"Paciente no encontrado"}
     * @response 422 {"success":false,"errors":{...}}
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['required', 'string', 'min:3'],
        ]);

        $term = trim($request->string('search')->value());

        if (ctype_digit($term)) {
            $patient = $this->patientService->findByDocument($term);

            if ($patient === null) {
                return $this->error('Paciente no encontrado en MedSys', 404);
            }

            $appointments = $this->appointmentService
                ->getPatientSummary($patient->codigo)
                ->map(fn (object $appt) => $this->enrichAppointment($appt));

            return $this->success(
                ['patient' => $patient, 'appointments' => $appointments],
                'Paciente encontrado en MedSys',
            );
        }

        $patients = $this->patientService->findByName($term);

        return $this->success(
            ['patients' => $patients],
            'Resultados de búsqueda en MedSys',
        );
    }

    /**
     * Citas activas de un paciente en MedSys.
     *
     * @urlParam codigo string required Código del paciente en MedSys. Example: P00123
     * @queryParam date  string Filtrar por fecha (Y-m-d). Si se omite devuelve todas las activas.
     *
     * @response 200 {"success":true,"data":[{"codcontrol":"C001","fecha":"2026-08-20",...}]}
     */
    public function appointments(Request $request, string $codigo): JsonResponse
    {
        $date = $request->query('date');

        $appointments = $this->appointmentService
            ->getActiveAppointments($codigo, $date ?: null)
            ->map(fn (object $appt) => $this->enrichAppointment($appt));

        return $this->success($appointments, 'Citas activas del paciente en MedSys');
    }

    /**
     * Listar tipos de procedimiento activos de MedSys con su mapeo a servicios de SGA.
     *
     * Usado en la pantalla de administración para vincular `tiposproc.codigo`
     * con `medical_services.external_code`.
     *
     * @response 200 {"success":true,"data":[{"codigo":"DER","descripcion":"Dermatología","medical_service_id":null,...}]}
     */
    public function procedureTypes(): JsonResponse
    {
        $types = $this->patientService->listProcedureTypes();

        $mappedCodes = MedicalServiceModel::whereNotNull('external_code')
            ->pluck('id', 'external_code');

        $data = $types->map(function (object $t) use ($mappedCodes) {
            $t->medical_service_id = $mappedCodes->get($t->codigo);
            $t->is_mapped          = $mappedCodes->has($t->codigo);
            return $t;
        });

        return $this->success($data, 'Tipos de procedimiento MedSys');
    }

    /**
     * Proyección de consumo de insumos basada en citas futuras + promedio histórico.
     *
     * @queryParam days          integer Horizonte de proyección (default 30).
     * @queryParam historical_days integer Período histórico para el promedio (default 90).
     *
     * @response 200 {"success":true,"data":[{"service_name":"...","projected_units":5,...}]}
     */
    public function consumptionProjection(Request $request, ConsumptionProjectionService $projectionService): JsonResponse
    {
        $days           = (int) $request->query('days', 30);
        $historicalDays = (int) $request->query('historical_days', 90);

        $projection = $projectionService->project($days, $historicalDays);

        return $this->success($projection, 'Proyección de consumo basada en citas futuras');
    }

    private function enrichAppointment(object $appt): object
    {
        $service = MedicalServiceModel::where('external_code', $appt->codtipocontrol)->first();

        $appt->medical_service_id   = $service?->id;
        $appt->medical_service_name = $service?->name;
        $appt->is_mapped            = $service !== null;

        return $appt;
    }
}
