<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Repositories\MedicalServiceRepositoryInterface;
use App\Modules\CostCenter\Domain\Repositories\ProcedurePriceRepositoryInterface;

class ListProcedurePricesUseCase
{
    public function __construct(
        private readonly ProcedurePriceRepositoryInterface $repository,
        private readonly MedicalServiceRepositoryInterface $serviceRepository,
    ) {}

    public function execute(int $procedureId, array $filters = []): array
    {
        $procedure = $this->serviceRepository->findById($procedureId);

        if ($procedure === null) {
            throw new \DomainException("Procedimiento con id {$procedureId} no encontrado.");
        }

        if (! $procedure->isProcedure()) {
            throw new \DomainException("El registro con id {$procedureId} es un servicio, no un procedimiento.");
        }

        return $this->repository->findByProcedureId($procedureId, $filters);
    }
}
