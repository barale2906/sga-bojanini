<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Resources;

use App\Modules\Assets\Infrastructure\Persistence\Models\AssetTechnicalProfileModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AssetTechnicalProfileModel */
class AssetTechnicalProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $values = ['asset_id' => $this->asset_id];

        foreach (AssetTechnicalProfileModel::FIELDS as $field) {
            $values[$field] = $this->{$field};
        }

        return [...$values, 'updated_at' => $this->updated_at?->toIso8601String()];
    }
}
