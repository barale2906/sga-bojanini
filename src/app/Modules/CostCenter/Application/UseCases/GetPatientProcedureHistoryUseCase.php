<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;

/**
 * Historial de procedimientos ejecutados a un paciente, con resumen agregado.
 *
 * Retorna los registros enriquecidos (con nombre del procedimiento, del servicio padre
 * y nombres del paciente) más un bloque de resumen calculado en memoria.
 */
class GetPatientProcedureHistoryUseCase
{
    public function __construct(
        private readonly PatientProcedureRecordRepositoryInterface $repository,
    ) {}

    /**
     * @return array{
     *   patient_external_id: string,
     *   patient_document: string|null,
     *   patient_first_name: string|null,
     *   patient_last_name: string|null,
     *   summary: array{
     *     total_records: int,
     *     total_amount: float,
     *     first_service_date: string|null,
     *     last_service_date: string|null,
     *   },
     *   records: array<int, array<string, mixed>>
     * }
     */
    public function execute(string $patientExternalId, array $filters = []): array
    {
        $records = $this->repository->findByPatientWithService($patientExternalId, $filters);

        $first = $records[0] ?? [];

        $dates  = array_column($records, 'service_date');
        $totals = array_column($records, 'total');

        return [
            'patient_external_id' => $patientExternalId,
            'patient_document'    => $first['patient_document'] ?? null,
            'patient_first_name'  => $first['patient_first_name'] ?? null,
            'patient_last_name'   => $first['patient_last_name'] ?? null,
            'summary'             => [
                'total_records'      => count($records),
                'total_amount'       => round(array_sum($totals), 2),
                'first_service_date' => ! empty($dates) ? min($dates) : null,
                'last_service_date'  => ! empty($dates) ? max($dates) : null,
            ],
            'records' => $records,
        ];
    }
}
