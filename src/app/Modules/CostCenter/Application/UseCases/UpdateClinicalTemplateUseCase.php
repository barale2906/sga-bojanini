<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Application\DTOs\ClinicalTemplateData;
use App\Modules\CostCenter\Domain\Entities\ClinicalTemplate;
use App\Modules\CostCenter\Domain\Repositories\ClinicalTemplateRepositoryInterface;

class UpdateClinicalTemplateUseCase
{
    public function __construct(
        private readonly ClinicalTemplateRepositoryInterface $repository,
    ) {}

    public function execute(int $id, ClinicalTemplateData $data): ClinicalTemplate
    {
        $template = $this->repository->findById($id);

        if ($template === null) {
            throw new \DomainException("Plantilla con id {$id} no encontrada.");
        }

        $template->update($data->title, $data->content, $data->isActive);

        return $this->repository->save($template);
    }
}
