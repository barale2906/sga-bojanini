<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Repositories\MedicalServiceRepositoryInterface;

class ListProceduresByServiceUseCase
{
    public function __construct(
        private readonly MedicalServiceRepositoryInterface $repository,
    ) {}

    public function execute(int $serviceId): array
    {
        $service = $this->repository->findById($serviceId);

        if ($service === null) {
            throw new \DomainException("Servicio médico con id {$serviceId} no encontrado.");
        }

        if ($service->isProcedure()) {
            throw new \DomainException('Los procedimientos no pueden contener otros procedimientos.');
        }

        return $this->repository->findProceduresByServiceId($serviceId);
    }
}
