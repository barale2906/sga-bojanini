<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Repositories;

use App\Modules\Assets\Domain\Entities\AssetSite;

interface AssetSiteRepositoryInterface
{
    public function findById(int $id): ?AssetSite;

    public function findByName(string $name): ?AssetSite;

    public function save(AssetSite $site): AssetSite;

    public function delete(int $id): void;

    public function hasPlaces(int $id): bool;
}
