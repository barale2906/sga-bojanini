<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\DTOs;

readonly class AssetPlaceData
{
    public function __construct(
        public int $siteId,
        public string $name,
        public bool $isActive = true,
    ) {}
}
