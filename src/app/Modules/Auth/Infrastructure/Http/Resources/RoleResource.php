<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'permissions' => $this->whenLoaded('permissions', fn () =>
                $this->permissions->pluck('name')
            ),
            // users_count viene de addSelect (subconsulta en model_has_roles),
            // no de withCount, por eso usamos el atributo directo.
            'users_count' => (int) ($this->users_count ?? 0),
        ];
    }
}
