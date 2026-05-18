<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $category = $this->resource;

        if (method_exists($category, 'getId')) {
            return [
                'id'          => $category->getId(),
                'parent_id'   => $category->getParentId(),
                'name'        => $category->getName(),
                'code'        => $category->getCode(),
                'description' => $category->getDescription(),
                'is_active'   => $category->isActive(),
            ];
        }

        $data = [
            'id'          => $category->id,
            'parent_id'   => $category->parent_id,
            'name'        => $category->name,
            'code'        => $category->code,
            'description' => $category->description,
            'is_active'   => (bool) $category->is_active,
            'created_at'  => $category->created_at?->toIso8601String(),
            'updated_at'  => $category->updated_at?->toIso8601String(),
        ];

        if ($category->relationLoaded('children')) {
            $data['children'] = CategoryResource::collection($category->children);
        }

        return $data;
    }
}
