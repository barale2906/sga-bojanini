<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovementDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'document_number'     => $this->document_number,
            'document_type'       => $this->document_type,
            'warehouse_id'        => $this->warehouse_id,
            'warehouse_to_id'     => $this->warehouse_to_id,
            'cost_center_id'      => $this->cost_center_id,
            'service_id'          => $this->service_id,
            'patient_document'    => $this->patient_document,
            'patient_external_id' => $this->patient_external_id,
            'invoice_number'      => $this->invoice_number,
            'entry_temperature'   => $this->entry_temperature,
            'reason'              => $this->reason,
            'user_id'             => $this->user_id,
            'status'              => $this->status instanceof \App\Modules\Inventory\Domain\ValueObjects\MovementStatus
                ? $this->status->value
                : $this->status,
            'created_at'          => $this->created_at?->toIso8601String(),

            'warehouse_name'   => $this->relationLoaded('warehouse') && $this->warehouse
                ? $this->warehouse->name
                : null,
            'warehouse_to_name' => $this->relationLoaded('warehouseTo') && $this->warehouseTo
                ? $this->warehouseTo->name
                : null,
            'user_name'        => $this->relationLoaded('user') && $this->user
                ? $this->user->name
                : null,
            'cost_center'      => $this->relationLoaded('costCenter') && $this->costCenter
                ? ['id' => $this->costCenter->id, 'code' => $this->costCenter->code, 'name' => $this->costCenter->name, 'type' => $this->costCenter->type]
                : null,
            'medical_service'  => $this->relationLoaded('medicalService') && $this->medicalService
                ? ['id' => $this->medicalService->id, 'code' => $this->medicalService->code, 'name' => $this->medicalService->name]
                : null,
            'signatures'       => $this->relationLoaded('signatures')
                ? MovementSignatureResource::collection($this->signatures)
                : null,
            'movements'        => $this->relationLoaded('movements')
                ? MovementResource::collection($this->movements)
                : null,
        ];
    }
}
