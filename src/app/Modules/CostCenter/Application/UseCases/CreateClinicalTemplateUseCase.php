<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Application\DTOs\ClinicalTemplateData;
use App\Modules\CostCenter\Domain\Entities\ClinicalTemplate;
use App\Modules\CostCenter\Domain\Repositories\ClinicalTemplateRepositoryInterface;
use App\Modules\CostCenter\Domain\Repositories\MedicalServiceRepositoryInterface;

class CreateClinicalTemplateUseCase
{
    public function __construct(
        private readonly ClinicalTemplateRepositoryInterface $repository,
        private readonly MedicalServiceRepositoryInterface $serviceRepository,
    ) {}

    public function execute(ClinicalTemplateData $data): ClinicalTemplate
    {
        $service = $this->serviceRepository->findById($data->medicalServiceId);

        if ($service === null) {
            throw new \DomainException("Servicio/procedimiento con id {$data->medicalServiceId} no encontrado.");
        }

        $existing = $this->repository->findByServiceId($data->medicalServiceId);

        if ($existing !== null) {
            throw new \DomainException("Ya existe una plantilla para '{$service->getName()}'. Edítela en lugar de crear una nueva.");
        }

        $template = ClinicalTemplate::create(
            medicalServiceId: $data->medicalServiceId,
            title:            $data->title,
            content:          $data->content,
        );

        return $this->repository->save($template);
    }
}
