<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Domain\Repositories;

use App\Modules\CostCenter\Domain\Entities\ProcedurePrice;

interface ProcedurePriceRepositoryInterface
{
    public function findById(int $id): ?ProcedurePrice;

    /** @return ProcedurePrice[] */
    public function findByProcedureId(int $medicalServiceId, array $filters = []): array;

    /** Precio vigente a una fecha dada (o hoy si null). */
    public function findCurrentPrice(int $medicalServiceId, ?\DateTimeImmutable $date = null): ?ProcedurePrice;

    public function save(ProcedurePrice $price): ProcedurePrice;

    public function delete(int $id): void;
}
