<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\DTOs;

use DateTimeImmutable;

/** DTO para crear o actualizar un registro sanitario de producto. */
class ProductSanitaryRegistrationData
{
    public function __construct(
        public readonly int $productVariantId,
        public readonly string $registrationNumber,
        public readonly DateTimeImmutable $expiryDate,
        public readonly bool $isActive = true,
    ) {}
}
