<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Repositories\MedicalServiceRepositoryInterface;

/**
 * Retorna el árbol completo de servicios con sus procedimientos anidados.
 * Útil para selectores en formularios de salidas de inventario.
 */
class GetMedicalServiceTreeUseCase
{
    public function __construct(
        private readonly MedicalServiceRepositoryInterface $repository,
    ) {}

    public function execute(bool $onlyActive = false): array
    {
        return $this->repository->findTreeWithProcedures($onlyActive);
    }
}
