<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Resources;

use App\Modules\Assets\Domain\Enums\AssetStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'type'              => $this->type->value,
            'type_label'        => $this->type->label(),
            'from_status'       => $this->from_status,
            'from_status_label' => $this->from_status ? AssetStatus::from($this->from_status)->label() : null,
            'to_status'         => $this->to_status,
            'to_status_label'   => $this->to_status ? AssetStatus::from($this->to_status)->label() : null,
            'description'       => $this->description,
            'transfer_id'       => $this->transfer_id,
            'maintenance_id'    => $this->maintenance_id,
            'user'              => UserSummary::of($this->user),
            'created_at'        => $this->created_at?->toIso8601String(),
        ];
    }
}
