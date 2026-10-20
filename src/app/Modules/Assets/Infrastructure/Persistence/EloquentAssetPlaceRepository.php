<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Persistence;

use App\Modules\Assets\Domain\Entities\AssetPlace;
use App\Modules\Assets\Domain\Repositories\AssetPlaceRepositoryInterface;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetLocationHistoryModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetPlaceModel;

class EloquentAssetPlaceRepository implements AssetPlaceRepositoryInterface
{
    public function findById(int $id): ?AssetPlace
    {
        $model = AssetPlaceModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findBySiteAndName(int $siteId, string $name): ?AssetPlace
    {
        $model = AssetPlaceModel::where('site_id', $siteId)->where('name', $name)->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(AssetPlace $place): AssetPlace
    {
        $model = $place->getId() ? AssetPlaceModel::findOrFail($place->getId()) : new AssetPlaceModel();

        $model->fill([
            'site_id'   => $place->getSiteId(),
            'name'      => $place->getName(),
            'is_active' => $place->isActive(),
        ])->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        AssetPlaceModel::findOrFail($id)->delete();
    }

    public function isInUse(int $id): bool
    {
        return AssetModel::withTrashed()->where('current_place_id', $id)->exists()
            || AssetLocationHistoryModel::where('place_id', $id)->exists();
    }

    private function toDomain(AssetPlaceModel $model): AssetPlace
    {
        return new AssetPlace(
            id:       $model->id,
            siteId:   $model->site_id,
            name:     $model->name,
            isActive: (bool) $model->is_active,
        );
    }
}
