<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Resources;

use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AssetModel */
class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $category = $this->whenLoaded('category');
        $type     = $this->relationLoaded('category') ? $this->category->asset_type : null;

        return [
            'id'                    => $this->id,
            'code'                  => $this->code,
            'name'                  => $this->name,
            'description'           => $this->description,
            'category'              => $this->relationLoaded('category')
                ? ['id' => $category->id, 'code' => $category->code, 'name' => $category->name]
                : null,
            'asset_type'            => $type?->value,
            'asset_type_label'      => $type?->label(),
            'requires_maintenance'  => $type?->requiresMaintenance(),
            'status'                => $this->status->value,
            'status_label'          => $this->status->label(),
            'location'              => [
                'is_off_premises'       => (bool) $this->is_off_premises,
                'off_premises_location' => $this->off_premises_location,
                'place_id'              => $this->current_place_id,
                'place_name'            => $this->relationLoaded('currentPlace') ? $this->currentPlace?->name : null,
                'site_id'               => $this->relationLoaded('currentPlace') ? $this->currentPlace?->site_id : null,
                'site_name'             => $this->relationLoaded('currentPlace') ? $this->currentPlace?->site?->name : null,
            ],
            'responsible'           => $this->relationLoaded('responsible') ? UserSummary::of($this->responsible) : null,
            'brand'                 => $this->brand,
            'model'                 => $this->model,
            'serial'                => $this->serial,
            'acquisition_date'      => $this->acquisition_date?->toDateString(),
            'acquisition_value'     => $this->acquisition_value,
            'supplier'              => $this->relationLoaded('supplier') && $this->supplier
                ? ['id' => $this->supplier->id, 'name' => $this->supplier->name, 'tax_id' => $this->supplier->tax_id]
                : null,
            'supplier_id'           => $this->supplier_id,
            'invoice_number'        => $this->invoice_number,
            'warranty_until'        => $this->warranty_until?->toDateString(),
            'retired_at'            => $this->retired_at?->toDateString(),
            'retirement_reason'     => $this->retirement_reason,
            'notes'                 => $this->notes,
            'next_maintenance'      => $this->relationLoaded('nextMaintenance') && $this->nextMaintenance
                ? [
                    'id'             => $this->nextMaintenance->id,
                    'type'           => $this->nextMaintenance->maintenance_type->value,
                    'type_label'     => $this->nextMaintenance->maintenance_type->label(),
                    'status'         => $this->nextMaintenance->status->value,
                    'scheduled_date' => $this->nextMaintenance->scheduled_date->toDateString(),
                    'is_overdue'     => $this->nextMaintenance->scheduled_date->isPast() && ! $this->nextMaintenance->scheduled_date->isToday(),
                ]
                : null,
            'pending_transfer'      => $this->relationLoaded('pendingTransfer') && $this->pendingTransfer
                ? ['id' => $this->pendingTransfer->id, 'type' => $this->pendingTransfer->type->value, 'type_label' => $this->pendingTransfer->type->label()]
                : null,
            'technical_profile'     => $this->relationLoaded('technicalProfile') && $this->technicalProfile
                ? new AssetTechnicalProfileResource($this->technicalProfile)
                : null,
            'counts'                => $this->when(isset($this->comments_count), fn () => [
                'comments'          => $this->comments_count,
                'documents'         => $this->documents_count,
                'maintenance_plans' => $this->maintenance_plans_count,
            ]),
            'created_by'            => $this->relationLoaded('creator') ? UserSummary::of($this->creator) : null,
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}
