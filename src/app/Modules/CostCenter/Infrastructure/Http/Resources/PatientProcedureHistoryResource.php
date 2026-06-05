<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Representa el historial completo de procedimientos de un paciente.
 * $this->resource es el array que retorna GetPatientProcedureHistoryUseCase.
 */
class PatientProcedureHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource;

        return [
            'patient_external_id' => $data['patient_external_id'],
            'patient_document'    => $data['patient_document'],
            'summary'             => [
                'total_records'      => $data['summary']['total_records'],
                'total_amount'       => $data['summary']['total_amount'],
                'first_service_date' => $data['summary']['first_service_date'],
                'last_service_date'  => $data['summary']['last_service_date'],
            ],
            'records' => array_map(
                static fn (array $r): array => [
                    'id'                 => $r['id'],
                    'service_date'       => $r['service_date'],
                    'service_id'         => $r['service_id'],
                    'service_name'       => $r['service_name'],
                    'medical_service_id' => $r['medical_service_id'],
                    'procedure_code'     => $r['procedure_code'],
                    'procedure_name'     => $r['procedure_name'],
                    'quantity'           => $r['quantity'],
                    'unit_price'         => $r['unit_price'],
                    'total'              => $r['total'],
                    'notes'              => $r['notes'],
                    'is_active'          => $r['is_active'],
                ],
                $data['records'],
            ),
        ];
    }
}
