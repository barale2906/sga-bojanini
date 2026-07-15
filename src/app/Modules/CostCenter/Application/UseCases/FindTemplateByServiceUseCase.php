<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\UseCases;

use App\Modules\CostCenter\Domain\Entities\ClinicalTemplate;
use App\Modules\CostCenter\Domain\Repositories\ClinicalTemplateRepositoryInterface;
use App\Modules\CostCenter\Domain\Repositories\MedicalServiceRepositoryInterface;

/**
 * Busca la plantilla aplicable a un servicio/procedimiento.
 * Primero busca en el nodo exacto; si no existe, sube al padre.
 */
class FindTemplateByServiceUseCase
{
    public function __construct(
        private readonly ClinicalTemplateRepositoryInterface $templateRepository,
        private readonly MedicalServiceRepositoryInterface $serviceRepository,
    ) {}

    public function execute(int $medicalServiceId): ?ClinicalTemplate
    {
        $template = $this->templateRepository->findByServiceId($medicalServiceId);

        if ($template !== null && $template->isActive()) {
            return $template;
        }

        $service = $this->serviceRepository->findById($medicalServiceId);

        if ($service === null || $service->getParentId() === null) {
            return null;
        }

        $parentTemplate = $this->templateRepository->findByServiceId($service->getParentId());

        return ($parentTemplate !== null && $parentTemplate->isActive()) ? $parentTemplate : null;
    }
}
