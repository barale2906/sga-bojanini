<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\DTOs;

readonly class AssetSiteData
{
    public function __construct(
        public string $name,
        public ?string $address = null,
        public bool $isActive = true,
    ) {}
}
