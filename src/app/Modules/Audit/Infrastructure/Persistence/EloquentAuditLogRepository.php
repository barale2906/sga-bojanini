<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure\Persistence;

use App\Modules\Audit\Domain\Entities\AuditLog;
use App\Modules\Audit\Domain\Repositories\AuditLogRepositoryInterface;
use App\Modules\Audit\Infrastructure\Persistence\Models\AuditLogModel;
use Carbon\Carbon;
use DateTimeImmutable;

class EloquentAuditLogRepository implements AuditLogRepositoryInterface
{
    public function findById(int $id): ?AuditLog
    {
        $model = AuditLogModel::with('user')->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(array $filters = []): array
    {
        $query = AuditLogModel::query()->with('user')->orderByDesc('id');

        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        if (!empty($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        $perPage = (int) ($filters['per_page'] ?? 25);
        $perPage = min(max($perPage, 1), 100);
        $page    = (int) ($filters['page'] ?? 1);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => collect($paginator->items())
                ->map(fn (AuditLogModel $model) => $this->toDomain($model))
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ];
    }

    public function getSummary(Carbon $from, Carbon $to): array
    {
        $baseQuery = AuditLogModel::query()
            ->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        $total = (clone $baseQuery)->count();

        $byAction = (clone $baseQuery)
            ->selectRaw('action, COUNT(*) as total')
            ->groupBy('action')
            ->pluck('total', 'action')
            ->map(fn ($count) => (int) $count)
            ->all();

        $byType = (clone $baseQuery)
            ->selectRaw('auditable_type, COUNT(*) as total')
            ->groupBy('auditable_type')
            ->pluck('total', 'auditable_type')
            ->map(fn ($count) => (int) $count)
            ->all();

        return [
            'total'     => $total,
            'by_action' => $byAction,
            'by_type'   => $byType,
        ];
    }

    private function toDomain(AuditLogModel $model): AuditLog
    {
        return new AuditLog(
            id: $model->id,
            userId: $model->user_id,
            action: $model->action,
            auditableType: $model->auditable_type,
            auditableId: $model->auditable_id,
            oldValues: $model->old_values,
            newValues: $model->new_values,
            ipAddress: $model->ip_address,
            userAgent: $model->user_agent,
            createdAt: DateTimeImmutable::createFromMutable($model->created_at),
            userName: $model->user?->name,
        );
    }
}
