<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence;

use App\Modules\Assets\Domain\Entities\AssetSite;
use App\Modules\Assets\Domain\Repositories\AssetSiteRepositoryInterface;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetPlaceModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetSiteModel;

class EloquentAssetSiteRepository implements AssetSiteRepositoryInterface
{
    public function findById(int $id): ?AssetSite
    {
        $model = AssetSiteModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByName(string $name): ?AssetSite
    {
        $model = AssetSiteModel::where('name', $name)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(AssetSite $site): AssetSite
    {
        $model = $site->getId() ? AssetSiteModel::findOrFail($site->getId()) : new AssetSiteModel();

        $model->fill([
            'name'      => $site->getName(),
            'address'   => $site->getAddress(),
            'is_active' => $site->isActive(),
        ])->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        AssetSiteModel::findOrFail($id)->delete();
    }

    public function hasPlaces(int $id): bool
    {
        return AssetPlaceModel::where('site_id', $id)->exists();
    }

    private function toDomain(AssetSiteModel $model): AssetSite
    {
        return new AssetSite(
            id:       $model->id,
            name:     $model->name,
            address:  $model->address,
            isActive: (bool) $model->is_active,
        );
    }
}
