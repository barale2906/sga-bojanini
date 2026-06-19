<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])
            ),
            'permissions' => $this->whenLoaded('roles', fn () => $this->getAllPermissions()->pluck('name')
            ),
            'warehouses' => $this->whenLoaded('warehouses', fn () => $this->warehouses->map(fn ($warehouse) => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'code' => $warehouse->code,
            ])
            ),
            'sensors' => $this->whenLoaded('sensors', fn () => $this->sensors->map(fn ($sensor) => [
                'id' => $sensor->id,
                'name' => $sensor->name,
                'code' => $sensor->code,
            ])
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
