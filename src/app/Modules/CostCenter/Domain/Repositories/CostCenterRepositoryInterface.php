<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Domain\Repositories;

use App\Modules\CostCenter\Domain\Entities\CostCenter;

interface CostCenterRepositoryInterface
{
    public function findById(int $id): ?CostCenter;

    public function findByCode(string $code): ?CostCenter;

    /** @return CostCenter[] */
    public function findAll(array $filters = []): array;

    public function save(CostCenter $costCenter): CostCenter;

    public function delete(int $id): void;
}
