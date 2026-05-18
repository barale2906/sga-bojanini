<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain\Repositories;

use App\Modules\Audit\Domain\Entities\AuditLog;
use Carbon\Carbon;

interface AuditLogRepositoryInterface
{
    public function findById(int $id): ?AuditLog;

    /**
     * @return array{data: AuditLog[], meta: array<string, mixed>}
     */
    public function findAll(array $filters = []): array;

    /**
     * @return array{total: int, by_action: array<string, int>, by_type: array<string, int>}
     */
    public function getSummary(Carbon $from, Carbon $to): array;
}
