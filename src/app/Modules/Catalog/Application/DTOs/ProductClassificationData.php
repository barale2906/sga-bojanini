<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\DTOs;

/** DTO para crear o actualizar una clasificación de producto. */
class ProductClassificationData
{
    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly bool $hasSanitaryRegistration = false,
        public readonly bool $hasConcentration = false,
        public readonly bool $hasRiskLevel = false,
        public readonly bool $hasPharmaFields = false,
        public readonly bool $hasDeviceFields = false,
        public readonly bool $hasLabBrand = false,
    ) {}
}
