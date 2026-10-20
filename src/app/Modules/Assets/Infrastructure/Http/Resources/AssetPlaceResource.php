<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Resources;

use App\Modules\Assets\Domain\Entities\AssetPlace;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetPlaceModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetPlaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $place = $this->resource;

        if ($place instanceof AssetPlace) {
            return [
                'id'        => $place->getId(),
                'site_id'   => $place->getSiteId(),
                'name'      => $place->getName(),
                'is_active' => $place->isActive(),
            ];
        }

        /** @var AssetPlaceModel $place */
        return [
            'id'           => $place->id,
            'site_id'      => $place->site_id,
            'name'         => $place->name,
            'is_active'    => (bool) $place->is_active,
            'assets_count' => $this->whenCounted('assets'),
            'site_name'    => $place->relationLoaded('site') ? $place->site?->name : null,
            'full_name'    => $place->relationLoaded('site') ? $place->fullName() : null,
        ];
    }
}
