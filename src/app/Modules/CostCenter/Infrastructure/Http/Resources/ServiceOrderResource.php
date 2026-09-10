<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Resources;

use App\Modules\CostCenter\Domain\Entities\PatientProcedureRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data    = $this->resource;
        $records = $data['records'] ?? [];

        $totalAmount   = collect($records)->sum(fn ($r) => $r instanceof PatientProcedureRecord ? $r->getTotal() : ($r['total'] ?? 0));
        $totalDiscount = collect($records)->sum(fn ($r) => $r instanceof PatientProcedureRecord ? ($r->getDiscountAmount() ?? 0) : ($r['discount_amount'] ?? 0));
        $netTotal      = $totalAmount - $totalDiscount;

        $hasPending = collect($records)->some(fn ($r) => $r instanceof PatientProcedureRecord
            ? $r->getDiscountStatus() === 'pending'
            : (($r['discount_status'] ?? null) === 'pending')
        );

        return [
            'order_number'        => $data['order_number'],
            'patient_external_id' => $data['patient_external_id'],
            'patient_document'    => $data['patient_document'],
            'patient_first_name'  => $data['patient_first_name'],
            'patient_last_name'   => $data['patient_last_name'],
            'patient_email'       => $data['patient_email'] ?? null,
            'patient_address'     => $data['patient_address'] ?? null,
            'patient_phone'       => $data['patient_phone'] ?? null,
            'service_date'        => $data['service_date'],
            'order_status'        => $hasPending ? 'discount_pending' : 'approved',
            'total_amount'        => round((float) $totalAmount, 2),
            'total_discount'      => round((float) $totalDiscount, 2),
            'net_total'           => round((float) $netTotal, 2),
            'created_by_user_id'  => $data['created_by_user_id'] ?? null,
            'procedures'          => PatientProcedureRecordResource::collection($records),
        ];
    }
}
