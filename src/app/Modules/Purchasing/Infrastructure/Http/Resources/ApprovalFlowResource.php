<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Resources;

use App\Modules\Purchasing\Infrastructure\Persistence\Models\ApprovalFlowModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ApprovalFlowModel */
class ApprovalFlowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'entity_type' => $this->entity_type,
            'conditions'  => $this->conditions,
            'is_active'   => $this->is_active,
            'steps'       => $this->whenLoaded('steps', fn () => $this->steps->map(fn ($step) => [
                'id'          => $step->id,
                'step_order'  => $step->step_order,
                'role_id'     => $step->role_id,
                'is_required' => $step->is_required,
                'role'        => $step->relationLoaded('role') ? [
                    'id'   => $step->role->id,
                    'name' => $step->role->name,
                ] : null,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
