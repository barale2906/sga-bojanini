<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\DTOs;

class CategoryData
{
    public function __construct(
        public readonly ?int $parentId,
        public readonly string $name,
        public readonly string $code,
        public readonly ?string $description = null,
    ) {}
}
