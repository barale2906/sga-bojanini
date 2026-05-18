<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $warehouse = $this->resource;

        if (method_exists($warehouse, 'getId')) {
            return [
                'id'          => $warehouse->getId(),
                'name'        => $warehouse->getName(),
                'code'        => $warehouse->getCode(),
                'address'     => $warehouse->getAddress(),
                'description' => $warehouse->getDescription(),
                'is_active'   => $warehouse->isActive(),
            ];
        }

        return [
            'id'          => $warehouse->id,
            'name'        => $warehouse->name,
            'code'        => $warehouse->code,
            'address'     => $warehouse->address,
            'description' => $warehouse->description,
            'is_active'   => $warehouse->is_active,
            'created_at'  => $warehouse->created_at?->toIso8601String(),
        ];
    }
}
