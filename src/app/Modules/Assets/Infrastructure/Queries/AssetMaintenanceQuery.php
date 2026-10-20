<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Queries;

use App\Modules\Assets\Domain\Enums\MaintenanceStatus;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenanceModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/** Cronograma de mantenimiento: listado/calendario de órdenes con filtros. */
class AssetMaintenanceQuery
{
    public const RELATIONS = ['asset.category', 'asset.currentPlace.site', 'plan', 'technician', 'supplier', 'registeredBy'];

    public function paginate(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with(self::RELATIONS)
            ->withCount('documents')
            ->orderBy('scheduled_date')
            ->orderBy('id')
            ->paginate(min(max($perPage, 1), 500));
    }

    /** @return \Illuminate\Support\Collection<int, AssetMaintenanceModel> */
    public function all(array $filters)
    {
        return $this->filtered($filters)->with(self::RELATIONS)->orderBy('scheduled_date')->get();
    }

    public function find(int $id): ?AssetMaintenanceModel
    {
        return AssetMaintenanceModel::with([...self::RELATIONS, 'documents.uploader', 'signatures'])->find($id);
    }

    private function filtered(array $filters): Builder
    {
        $query = AssetMaintenanceModel::query();

        if (! empty($filters['from'])) {
            $query->whereDate('scheduled_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('scheduled_date', '<=', $filters['to']);
        }

        foreach (['asset_id', 'status', 'maintenance_type', 'plan_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->whereIn($field, (array) $filters[$field]);
            }
        }

        if (filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->where('status', MaintenanceStatus::Scheduled->value)
                ->whereDate('scheduled_date', '<', now()->toDateString());
        }

        if (! empty($filters['site_id'])) {
            $query->whereHas('asset.currentPlace', fn ($q) => $q->where('site_id', $filters['site_id']));
        }

        if (! empty($filters['asset_type'])) {
            $query->whereHas('asset.category', fn ($q) => $q->where('asset_type', $filters['asset_type']));
        }

        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->whereHas('asset', fn ($q) => $q->where('code', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('serial', 'like', "%{$term}%"));
        }

        return $query;
    }
}
