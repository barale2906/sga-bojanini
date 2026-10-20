<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\DTOs;

readonly class AssetCategoryData
{
    public function __construct(
        public string $code,
        public string $name,
        public string $assetType,
        public ?string $description = null,
        public bool $isActive = true,
    ) {}
}
