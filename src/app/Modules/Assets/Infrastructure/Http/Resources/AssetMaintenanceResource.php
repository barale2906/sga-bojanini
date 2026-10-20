<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Resources;

use App\Modules\Assets\Domain\Enums\MaintenanceStatus;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenanceModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AssetMaintenanceModel */
class AssetMaintenanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $asset = $this->relationLoaded('asset') ? $this->asset : null;

        return [
            'id'                       => $this->id,
            'asset'                    => $asset ? [
                'id'         => $asset->id,
                'code'       => $asset->code,
                'name'       => $asset->name,
                'asset_type' => $asset->relationLoaded('category') ? $asset->category->asset_type->value : null,
                'location'   => $asset->relationLoaded('currentPlace')
                    ? ($asset->currentPlace?->fullName() ?? $asset->off_premises_location)
                    : null,
            ] : ['id' => $this->asset_id],
            'plan_id'                  => $this->plan_id,
            'maintenance_type'         => $this->maintenance_type->value,
            'maintenance_type_label'   => $this->maintenance_type->label(),
            'status'                   => $this->status->value,
            'status_label'             => $this->status->label(),
            'is_overdue'               => $this->status === MaintenanceStatus::Scheduled
                && $this->scheduled_date->isPast() && ! $this->scheduled_date->isToday(),
            'scheduled_date'           => $this->scheduled_date?->toDateString(),
            'started_at'               => $this->started_at?->toIso8601String(),
            'performed_at'             => $this->performed_at?->toIso8601String(),
            'performed_by_type'        => $this->performed_by_type?->value,
            'performed_by_type_label'  => $this->performed_by_type?->label(),
            'technician'               => $this->relationLoaded('technician') ? UserSummary::of($this->technician) : null,
            'supplier'                 => $this->relationLoaded('supplier') && $this->supplier
                ? ['id' => $this->supplier->id, 'name' => $this->supplier->name]
                : null,
            'external_technician_name' => $this->external_technician_name,
            'reported_failure'         => $this->reported_failure,
            'activities_performed'     => $this->activities_performed,
            'findings'                 => $this->findings,
            'recommendations'          => $this->recommendations,
            'parts_replaced'           => $this->parts_replaced ?? [],
            'cost'                     => $this->cost,
            'result'                   => $this->result?->value,
            'result_label'             => $this->result?->label(),
            'registered_by'            => $this->relationLoaded('registeredBy') ? UserSummary::of($this->registeredBy) : null,
            'cancel_reason'            => $this->cancel_reason,
            'documents_count'          => $this->whenCounted('documents'),
            'documents'                => AssetDocumentResource::collection($this->whenLoaded('documents')),
            'signatures'               => AssetSignatureResource::collection($this->whenLoaded('signatures')),
            'created_at'               => $this->created_at?->toIso8601String(),
        ];
    }
}
