<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Repositories\ClinicalTemplateRepositoryInterface;

class DeleteClinicalTemplateUseCase
{
    public function __construct(
        private readonly ClinicalTemplateRepositoryInterface $repository,
    ) {}

    public function execute(int $id): void
    {
        $template = $this->repository->findById($id);

        if ($template === null) {
            throw new \DomainException("Plantilla con id {$id} no encontrada.");
        }

        $this->repository->delete($id);
    }
}
