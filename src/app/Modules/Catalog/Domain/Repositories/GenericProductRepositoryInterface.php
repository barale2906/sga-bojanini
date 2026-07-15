<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Repositories;

use App\Modules\Catalog\Domain\Entities\GenericProduct;

interface GenericProductRepositoryInterface
{
    public function findById(int $id): ?GenericProduct;

    public function findByBarcode(string $barcode): ?GenericProduct;

    /** @return GenericProduct[] */
    public function findAll(array $filters = []): array;

    public function save(GenericProduct $product): GenericProduct;

    public function delete(int $id): void;

    public function getMaxBarcode(): int;
}
