<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Queries;

use App\Modules\Assets\Infrastructure\Persistence\Models\AssetTransferModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AssetTransferQuery
{
    public const RELATIONS = [
        'asset.category', 'fromPlace.site', 'toPlace.site', 'supplier',
        'fromResponsible', 'toResponsible', 'requester', 'signatures',
    ];

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = AssetTransferModel::query()->with(self::RELATIONS);

        foreach (['asset_id', 'status', 'type'] as $field) {
            if (! empty($filters[$field])) {
                $query->whereIn($field, (array) $filters[$field]);
            }
        }

        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->whereHas('asset', fn ($q) => $q->where('code', 'like', "%{$term}%")->orWhere('name', 'like', "%{$term}%"));
        }

        return $query->orderByDesc('id')->paginate(min(max($perPage, 1), 100));
    }

    public function find(int $id): ?AssetTransferModel
    {
        return AssetTransferModel::with(self::RELATIONS)->find($id);
    }
}
