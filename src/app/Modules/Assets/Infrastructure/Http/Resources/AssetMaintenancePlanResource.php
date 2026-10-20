<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Resources;

use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenancePlanModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AssetMaintenancePlanModel */
class AssetMaintenancePlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'asset_id'                => $this->asset_id,
            'maintenance_type'        => $this->maintenance_type->value,
            'maintenance_type_label'  => $this->maintenance_type->label(),
            'frequency_value'         => $this->frequency_value,
            'frequency_unit'          => $this->frequency_unit->value,
            'frequency_label'         => "Cada {$this->frequency_value} " . mb_strtolower($this->frequency_unit->label()),
            'start_date'              => $this->start_date?->toDateString(),
            'performed_by_type'       => $this->performed_by_type->value,
            'performed_by_type_label' => $this->performed_by_type->label(),
            'technician'              => $this->relationLoaded('technician') ? UserSummary::of($this->technician) : null,
            'supplier'                => $this->relationLoaded('supplier') && $this->supplier
                ? ['id' => $this->supplier->id, 'name' => $this->supplier->name]
                : null,
            'notes'                   => $this->notes,
            'is_active'               => (bool) $this->is_active,
            'next_date'               => $this->relationLoaded('openOrder') ? $this->openOrder?->scheduled_date?->toDateString() : null,
        ];
    }
}
