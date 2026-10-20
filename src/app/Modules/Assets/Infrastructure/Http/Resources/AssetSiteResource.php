<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Resources;

use App\Modules\Assets\Domain\Entities\AssetSite;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetSiteModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetSiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $site = $this->resource;

        if ($site instanceof AssetSite) {
            return [
                'id'        => $site->getId(),
                'name'      => $site->getName(),
                'address'   => $site->getAddress(),
                'is_active' => $site->isActive(),
            ];
        }

        /** @var AssetSiteModel $site */
        return [
            'id'        => $site->id,
            'name'      => $site->name,
            'address'   => $site->address,
            'is_active' => (bool) $site->is_active,
            'places'    => AssetPlaceResource::collection($this->whenLoaded('places')),
        ];
    }
}
