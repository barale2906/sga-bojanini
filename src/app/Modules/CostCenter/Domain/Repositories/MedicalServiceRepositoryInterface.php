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

    /**
     * Retorna servicios raíz (type=service, sin parent) con sus procedimientos anidados.
     * @return array<int, array<string, mixed>>
     */
    public function findTreeWithProcedures(bool $onlyActive = false): array;

    /** @return MedicalService[] */
    public function findProceduresByServiceId(int $serviceId): array;

    public function save(MedicalService $service): MedicalService;

    public function delete(int $id): void;
}
