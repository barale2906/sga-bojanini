<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Domain\Repositories;

use App\Modules\CostCenter\Domain\Entities\ClinicalTemplate;

interface ClinicalTemplateRepositoryInterface
{
    public function findById(int $id): ?ClinicalTemplate;

    public function findByServiceId(int $medicalServiceId): ?ClinicalTemplate;

    /** @return ClinicalTemplate[] */
    public function findAll(array $filters = []): array;

    public function save(ClinicalTemplate $template): ClinicalTemplate;

    public function delete(int $id): void;
}
