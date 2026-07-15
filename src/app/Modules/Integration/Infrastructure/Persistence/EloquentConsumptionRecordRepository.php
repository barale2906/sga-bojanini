<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\Persistence;

use App\Modules\Integration\Domain\Repositories\ConsumptionRecordRepositoryInterface;
use App\Modules\Integration\Infrastructure\Persistence\Models\ConsumptionRecordModel;
use Carbon\Carbon;

class EloquentConsumptionRecordRepository implements ConsumptionRecordRepositoryInterface
{
    public function findById(int $id): ?ConsumptionRecordModel
    {
        return ConsumptionRecordModel::with(['variant.genericProduct', 'batch', 'user'])->find($id);
    }

    public function findAll(array $filters = []): array
    {
        $query = ConsumptionRecordModel::with(['variant.genericProduct', 'batch', 'user'])
            ->orderByDesc('consumed_at');

        if (!empty($filters['sync_status'])) {
            $query->where('sync_status', $filters['sync_status']);
        }

        if (!empty($filters['appointment_id'])) {
            $query->where('appointment_id', $filters['appointment_id']);
        }

        if (!empty($filters['patient_identifier'])) {
            $query->where('patient_identifier', $filters['patient_identifier']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('consumed_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (!empty($filters['date_to'])) {
            $query->where('consumed_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        $perPage = min(max((int) ($filters['per_page'] ?? 25), 1), 100);
        $page    = max((int) ($filters['page'] ?? 1), 1);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ];
    }
}
