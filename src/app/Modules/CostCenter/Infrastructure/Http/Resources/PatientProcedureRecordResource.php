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
                'patient_email'        => $record->getPatientEmail(),
                'patient_address'      => $record->getPatientAddress(),
                'patient_phone'        => $record->getPatientPhone(),
                'quantity'             => $record->getQuantity(),
                'unit_price'           => $record->getUnitPrice(),
                'total'                => $record->getTotal(),
                'discount_type'        => $record->getDiscountType(),
                'discount_value'       => $record->getDiscountValue(),
                'discount_amount'      => $record->getDiscountAmount(),
                'net_total'            => $record->getNetTotal(),
                'discount_status'      => $record->getDiscountStatus(),
                'order_number'         => $record->getOrderNumber(),
                'created_by_user_id'   => $record->getCreatedByUserId(),
                'approved_by_user_id'  => $record->getApprovedByUserId(),
                'approved_at'          => $record->getApprovedAt()?->format('Y-m-d H:i:s'),
                'billing_status'       => $record->getBillingStatus(),
                'billed_by_user_id'    => $record->getBilledByUserId(),
                'billed_at'            => $record->getBilledAt()?->format('Y-m-d H:i:s'),
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
            'patient_email'        => $record->patient_email,
            'patient_address'      => $record->patient_address,
            'patient_phone'        => $record->patient_phone,
            'quantity'             => $record->quantity,
            'unit_price'           => $record->unit_price,
            'total'                => $record->total,
            'discount_type'        => $record->discount_type,
            'discount_value'       => $record->discount_value,
            'discount_amount'      => $record->discount_amount,
            'net_total'            => $record->net_total,
            'discount_status'      => $record->discount_status,
            'order_number'         => $record->order_number,
            'created_by_user_id'   => $record->created_by_user_id,
            'approved_by_user_id'  => $record->approved_by_user_id,
            'approved_at'          => $record->approved_at?->format('Y-m-d H:i:s'),
            'billing_status'       => $record->billing_status,
            'billed_by_user_id'    => $record->billed_by_user_id,
            'billed_at'            => $record->billed_at?->format('Y-m-d H:i:s'),
            'service_date'         => $record->service_date?->format('Y-m-d'),
            'seller'               => $record->seller,
            'referrer'             => $record->referrer,
            'is_active'            => $record->is_active,
        ];
    }
}
