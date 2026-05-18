<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitOfMeasureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unit = $this->resource;

        if (method_exists($unit, 'getId')) {
            return [
                'id'           => $unit->getId(),
                'name'         => $unit->getName(),
                'abbreviation' => $unit->getAbbreviation(),
                'is_active'    => $unit->isActive(),
                'is_base'      => $unit->isBase(),
            ];
        }

        return [
            'id'           => $unit->id,
            'name'         => $unit->name,
            'abbreviation' => $unit->abbreviation,
            'is_active'    => (bool) $unit->is_active,
            'is_base'      => (bool) $unit->is_base,
            'created_at'   => $unit->created_at?->toIso8601String(),
        ];
    }
}
