<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Resources;

use App\Modules\Assets\Domain\Entities\AssetCategory;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetCategoryModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $category = $this->resource;

        if ($category instanceof AssetCategory) {
            $type = $category->getAssetType();

            return [
                'id'                   => $category->getId(),
                'code'                 => $category->getCode(),
                'name'                 => $category->getName(),
                'asset_type'           => $type->value,
                'asset_type_label'     => $type->label(),
                'requires_maintenance' => $type->requiresMaintenance(),
                'description'          => $category->getDescription(),
                'is_active'            => $category->isActive(),
            ];
        }

        /** @var AssetCategoryModel $category */
        return [
            'id'                   => $category->id,
            'code'                 => $category->code,
            'name'                 => $category->name,
            'asset_type'           => $category->asset_type->value,
            'asset_type_label'     => $category->asset_type->label(),
            'requires_maintenance' => $category->asset_type->requiresMaintenance(),
            'description'          => $category->description,
            'is_active'            => (bool) $category->is_active,
        ];
    }
}
