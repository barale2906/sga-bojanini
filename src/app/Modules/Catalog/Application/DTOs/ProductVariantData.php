<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\DTOs;

class ProductVariantData
{
    public function __construct(
        public readonly int $genericProductId,
        public readonly ?string $labBrand = null,
        public readonly ?string $brandSku = null,
        public readonly ?string $commercialPresentation = null,
        public readonly ?string $serieReference = null,
        public readonly ?string $usefulLife = null,
        public readonly ?string $riskLevel = null,
    ) {}
}
