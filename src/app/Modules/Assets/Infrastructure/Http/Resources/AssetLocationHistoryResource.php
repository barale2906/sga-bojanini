<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetLocationHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'place_id'        => $this->place_id,
            'location'        => $this->place?->fullName() ?? $this->external_location,
            'is_off_premises' => $this->place_id === null,
            'from_date'       => $this->from_date?->toIso8601String(),
            'to_date'         => $this->to_date?->toIso8601String(),
            'is_current'      => $this->to_date === null,
            'transfer_id'     => $this->transfer_id,
            'transfer_type'   => $this->transfer?->type->value,
            'transfer_label'  => $this->transfer?->type->label(),
            'assigned_by'     => UserSummary::of($this->assignedBy),
        ];
    }
}
