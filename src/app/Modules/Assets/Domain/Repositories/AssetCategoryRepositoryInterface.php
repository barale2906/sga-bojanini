<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Repositories;

use App\Modules\Assets\Domain\Entities\AssetCategory;

interface AssetCategoryRepositoryInterface
{
    public function findById(int $id): ?AssetCategory;

    public function findByCode(string $code): ?AssetCategory;

    /** @return AssetCategory[] */
    public function findAll(array $filters = []): array;

    public function save(AssetCategory $category): AssetCategory;

    public function delete(int $id): void;

    public function hasAssets(int $id): bool;
}
