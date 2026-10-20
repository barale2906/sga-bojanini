<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Resources;

use App\Modules\Assets\Infrastructure\Persistence\Models\AssetTransferModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AssetTransferModel */
class AssetTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'asset'                => $this->relationLoaded('asset') && $this->asset
                ? ['id' => $this->asset->id, 'code' => $this->asset->code, 'name' => $this->asset->name]
                : ['id' => $this->asset_id],
            'type'                 => $this->type->value,
            'type_label'           => $this->type->label(),
            'status'               => $this->status->value,
            'status_label'         => $this->status->label(),
            'requires_signature'   => (bool) $this->requires_signature,
            'from'                 => $this->relationLoaded('fromPlace') ? ($this->fromPlace?->fullName() ?? $this->from_external_location) : null,
            'to'                   => $this->relationLoaded('toPlace') ? ($this->toPlace?->fullName() ?? $this->external_destination) : null,
            'from_place_id'        => $this->from_place_id,
            'to_place_id'          => $this->to_place_id,
            'external_destination' => $this->external_destination,
            'supplier'             => $this->relationLoaded('supplier') && $this->supplier
                ? ['id' => $this->supplier->id, 'name' => $this->supplier->name]
                : null,
            'from_responsible'     => $this->relationLoaded('fromResponsible') ? UserSummary::of($this->fromResponsible) : null,
            'to_responsible'       => $this->relationLoaded('toResponsible') ? UserSummary::of($this->toResponsible) : null,
            'expected_return_date' => $this->expected_return_date?->toDateString(),
            'resulting_status'     => $this->resulting_status,
            'reason'               => $this->reason,
            'requested_by'         => $this->relationLoaded('requester') ? UserSummary::of($this->requester) : null,
            'confirmed_at'         => $this->confirmed_at?->toIso8601String(),
            'cancel_reason'        => $this->cancel_reason,
            'signatures'           => AssetSignatureResource::collection($this->whenLoaded('signatures')),
            'created_at'           => $this->created_at?->toIso8601String(),
        ];
    }
}
