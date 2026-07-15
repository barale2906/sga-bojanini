<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Application\DTOs;

class ClinicalTemplateData
{
    public function __construct(
        public readonly int $medicalServiceId,
        public readonly string $title,
        public readonly string $content,
        public readonly bool $isActive = true,
    ) {}
}
