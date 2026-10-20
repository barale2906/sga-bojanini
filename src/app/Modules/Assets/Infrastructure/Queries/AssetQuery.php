<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Queries;

use App\Modules\Assets\Domain\Enums\AssetStatus;
use App\Modules\Assets\Domain\Enums\AssetTransferStatus;
use App\Modules\Assets\Domain\Enums\MaintenanceStatus;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetMaintenanceModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetModel;
use App\Modules\Assets\Infrastructure\Persistence\Models\AssetTransferModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lado de lectura del módulo de activos: listados con filtros, ficha y
 * estadísticas. Las escrituras pasan por los casos de uso.
 */
class AssetQuery
{
    public const SORTABLE = ['code', 'name', 'status', 'acquisition_date', 'warranty_until', 'created_at'];

    /** Relaciones necesarias para pintar un activo en listados. */
    public const LIST_RELATIONS = ['category', 'currentPlace.site', 'responsible', 'nextMaintenance', 'pendingTransfer'];

    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $sortBy  = in_array($filters['sort_by'] ?? null, self::SORTABLE, true) ? $filters['sort_by'] : 'code';
        $sortDir = ($filters['sort_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $this->filtered($filters)
            ->with(self::LIST_RELATIONS)
            ->orderBy($sortBy, $sortDir)
            ->paginate(min(max($perPage, 1), 100));
    }

    /** @return \Illuminate\Support\Collection<int, AssetModel> todos los que cumplen los filtros (exportación) */
    public function all(array $filters)
    {
        return $this->filtered($filters)->with([...self::LIST_RELATIONS, 'supplier'])->orderBy('code')->get();
    }

    public function find(int $id): ?AssetModel
    {
        return AssetModel::with([...self::LIST_RELATIONS, 'supplier', 'creator', 'technicalProfile'])
            ->withCount(['comments', 'documents', 'maintenancePlans'])
            ->find($id);
    }

    /**
     * Indicadores del inventario de activos. `by_status` incluye los dados de
     * baja; el resto de indicadores considera solo activos vigentes.
     */
    public function statistics(array $filters): array
    {
        unset($filters['status']);

        $all  = fn () => $this->filtered([...$filters, 'include_retired' => true]);
        $base = fn () => $this->filtered([...$filters, 'include_retired' => false]);

        $byStatus = $all()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        $byType = $base()
            ->join('asset_categories', 'asset_categories.id', '=', 'assets.category_id')
            ->selectRaw('asset_categories.asset_type as asset_type, COUNT(*) as total')
            ->groupBy('asset_categories.asset_type')
            ->pluck('total', 'asset_type')
            ->all();

        $bySite = $base()
            ->leftJoin('asset_places', 'asset_places.id', '=', 'assets.current_place_id')
            ->leftJoin('asset_sites', 'asset_sites.id', '=', 'asset_places.site_id')
            ->selectRaw('asset_sites.id as site_id, asset_sites.name as site, COUNT(*) as total')
            ->groupBy('asset_sites.id', 'asset_sites.name')
            ->orderBy('asset_sites.name')
            ->get()
            ->map(fn ($row) => ['site_id' => $row->site_id, 'site' => $row->site ?? 'Fuera de las instalaciones', 'total' => (int) $row->total])
            ->all();

        $assetIds = fn () => $base()->select('assets.id');
        $today    = now()->toDateString();

        return [
            'total'             => array_sum($byStatus) - (int) ($byStatus[AssetStatus::Retired->value] ?? 0),
            'by_status'         => collect(AssetStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($byStatus[$s->value] ?? 0)])->all(),
            'by_type'           => $byType,
            'by_site'           => $bySite,
            'off_premises'      => $base()->where('is_off_premises', true)->count(),
            'total_value'       => (float) $base()->sum('acquisition_value'),
            'pending_transfers' => AssetTransferModel::whereIn('asset_id', $assetIds())
                ->where('status', AssetTransferStatus::PendingSignature->value)->count(),
            'maintenance'       => [
                'overdue'     => AssetMaintenanceModel::whereIn('asset_id', $assetIds())
                    ->where('status', MaintenanceStatus::Scheduled->value)->whereDate('scheduled_date', '<', $today)->count(),
                'upcoming'    => AssetMaintenanceModel::whereIn('asset_id', $assetIds())
                    ->where('status', MaintenanceStatus::Scheduled->value)
                    ->whereBetween('scheduled_date', [$today, now()->addDays(15)->toDateString()])->count(),
                'in_progress' => AssetMaintenanceModel::whereIn('asset_id', $assetIds())
                    ->where('status', MaintenanceStatus::InProgress->value)->count(),
            ],
        ];
    }

    private function filtered(array $filters): Builder
    {
        $query = AssetModel::query();

        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(fn ($q) => $q->where('assets.code', 'like', "%{$term}%")
                ->orWhere('assets.name', 'like', "%{$term}%")
                ->orWhere('assets.serial', 'like', "%{$term}%")
                ->orWhere('assets.brand', 'like', "%{$term}%")
                ->orWhere('assets.model', 'like', "%{$term}%"));
        }

        foreach (['category_id', 'responsible_id', 'status'] as $field) {
            if (! empty($filters[$field])) {
                $query->whereIn("assets.{$field}", (array) $filters[$field]);
            }
        }

        if (! empty($filters['place_id'])) {
            $query->where('assets.current_place_id', $filters['place_id']);
        }

        if (! empty($filters['site_id'])) {
            $query->whereHas('currentPlace', fn ($q) => $q->where('site_id', $filters['site_id']));
        }

        if (! empty($filters['asset_type'])) {
            $query->whereHas('category', fn ($q) => $q->where('asset_type', $filters['asset_type']));
        }

        if (isset($filters['is_off_premises']) && $filters['is_off_premises'] !== '') {
            $query->where('assets.is_off_premises', filter_var($filters['is_off_premises'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! filter_var($filters['include_retired'] ?? false, FILTER_VALIDATE_BOOLEAN) && empty($filters['status'])) {
            $query->where('assets.status', '!=', AssetStatus::Retired->value);
        }

        if (! empty($filters['warranty_expiring_days'])) {
            $query->whereBetween('assets.warranty_until', [now()->toDateString(), now()->addDays((int) $filters['warranty_expiring_days'])->toDateString()]);
        }

        if (filter_var($filters['maintenance_overdue'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereHas('maintenances', fn ($q) => $q->where('status', MaintenanceStatus::Scheduled->value)
                ->whereDate('scheduled_date', '<', now()->toDateString()));
        }

        return $query;
    }
}
