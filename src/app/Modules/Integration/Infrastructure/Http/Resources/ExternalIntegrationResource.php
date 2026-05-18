<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\Http\Resources;

use App\Modules\Integration\Infrastructure\Persistence\Models\ExternalIntegrationModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExternalIntegrationModel */
class ExternalIntegrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'type'         => $this->type,
            'base_url'     => $this->base_url,
            'auth_type'    => $this->auth_type,
            'is_active'    => $this->is_active,
            'last_sync_at' => $this->last_sync_at?->format('Y-m-d H:i:s'),
            'created_at'   => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'   => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
