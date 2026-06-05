<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;

class DeletePatientProcedureRecordUseCase
{
    public function __construct(
        private readonly PatientProcedureRecordRepositoryInterface $repository,
    ) {}

    public function execute(int $id): void
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException("Registro de procedimiento con id {$id} no encontrado.");
        }

        $this->repository->delete($id);
    }
}
