<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;

/**
 * Historial de procedimientos ejecutados a un paciente, con resumen agregado.
 *
 * Retorna los registros enriquecidos (con nombre del procedimiento y del servicio padre)
 * más un bloque de resumen calculado en memoria para evitar múltiples queries.
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

        $patientDocument = $records[0]['patient_document'] ?? null;

        $dates  = array_column($records, 'service_date');
        $totals = array_column($records, 'total');

        return [
            'patient_external_id' => $patientExternalId,
            'patient_document'    => $patientDocument,
            'summary'             => [
                'total_records'     => count($records),
                'total_amount'      => round(array_sum($totals), 2),
                'first_service_date' => ! empty($dates) ? min($dates) : null,
                'last_service_date'  => ! empty($dates) ? max($dates) : null,
            ],
            'records' => $records,
        ];
    }
}
