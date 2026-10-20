<?php

declare(strict_types=1);

namespace App\Modules\Assets\Domain\Repositories;

use App\Modules\Assets\Domain\Entities\AssetPlace;

interface AssetPlaceRepositoryInterface
{
    public function findById(int $id): ?AssetPlace;

    public function findBySiteAndName(int $siteId, string $name): ?AssetPlace;

    public function save(AssetPlace $place): AssetPlace;

    public function delete(int $id): void;

    /** Si el sitio tiene activos actualmente o figura en su historial de ubicaciones. */
    public function isInUse(int $id): bool;
}
