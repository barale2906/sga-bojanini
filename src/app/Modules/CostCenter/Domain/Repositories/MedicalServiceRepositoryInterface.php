<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Domain\Repositories;

use App\Modules\CostCenter\Domain\Entities\MedicalService;

interface MedicalServiceRepositoryInterface
{
    public function findById(int $id): ?MedicalService;

    public function findByCode(string $code): ?MedicalService;

    /** @return MedicalService[] */
    public function findAll(array $filters = []): array;

    public function save(MedicalService $service): MedicalService;

    public function delete(int $id): void;
}
