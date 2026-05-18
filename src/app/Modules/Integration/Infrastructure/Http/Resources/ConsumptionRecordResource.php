<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\Http\Resources;

use App\Modules\Integration\Infrastructure\Persistence\Models\ConsumptionRecordModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ConsumptionRecordModel */
class ConsumptionRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'appointment_id'     => $this->appointment_id,
            'patient_identifier' => $this->patient_identifier,
            'service_type'       => $this->service_type,
            'product_id'         => $this->product_id,
            'product_code'       => $this->product?->code,
            'product_name'       => $this->product?->name,
            'batch_id'           => $this->batch_id,
            'lot_number'         => $this->batch?->lot_number,
            'quantity'           => $this->quantity,
            'user_id'            => $this->user_id,
            'consumed_at'        => $this->consumed_at?->format('Y-m-d H:i:s'),
            'sync_status'        => $this->sync_status,
            'synced_to_hc_at'    => $this->synced_to_hc_at?->format('Y-m-d H:i:s'),
        ];
    }
}
