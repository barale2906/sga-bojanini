<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Resources;

use App\Modules\CostCenter\Domain\Entities\PatientProcedureRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientProcedureRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $record = $this->resource;

        if ($record instanceof PatientProcedureRecord) {
            return [
                'id'                   => $record->getId(),
                'medical_service_id'   => $record->getMedicalServiceId(),
                'medical_service_name' => $record->getMedicalServiceName(),
                'movement_document_id' => $record->getMovementDocumentId(),
                'patient_external_id'  => $record->getPatientExternalId(),
                'patient_document'     => $record->getPatientDocument(),
                'patient_first_name'   => $record->getPatientFirstName(),
                'patient_last_name'    => $record->getPatientLastName(),
                'quantity'             => $record->getQuantity(),
                'unit_price'           => $record->getUnitPrice(),
                'total'                => $record->getTotal(),
                'service_date'         => $record->getServiceDate()->format('Y-m-d'),
                'seller'               => $record->getSeller(),
                'referrer'             => $record->getReferrer(),
                'is_active'            => $record->isActive(),
            ];
        }

        return [
            'id'                   => $record->id,
            'medical_service_id'   => $record->medical_service_id,
            'medical_service_name' => $record->medicalService?->name,
            'movement_document_id' => $record->movement_document_id,
            'patient_external_id'  => $record->patient_external_id,
            'patient_document'     => $record->patient_document,
            'patient_first_name'   => $record->patient_first_name,
            'patient_last_name'    => $record->patient_last_name,
            'quantity'             => $record->quantity,
            'unit_price'           => $record->unit_price,
            'total'                => $record->total,
            'service_date'         => $record->service_date?->format('Y-m-d'),
            'seller'               => $record->seller,
            'referrer'             => $record->referrer,
            'is_active'            => $record->is_active,
        ];
    }
}
