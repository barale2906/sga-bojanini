<?php

declare(strict_types=1);

namespace App\Modules\Integration\Domain\Repositories;

use App\Modules\Integration\Infrastructure\Persistence\Models\ConsumptionRecordModel;

interface ConsumptionRecordRepositoryInterface
{
    public function findById(int $id): ?ConsumptionRecordModel;

    /**
     * @return array{data: ConsumptionRecordModel[], meta: array<string, mixed>}
     */
    public function findAll(array $filters = []): array;
}
