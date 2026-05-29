<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Repositories;

use App\Modules\Catalog\Domain\Entities\ProductSanitaryRegistration;

interface ProductSanitaryRegistrationRepositoryInterface
{
    public function findById(int $id): ?ProductSanitaryRegistration;

    /** @return ProductSanitaryRegistration[] */
    public function findByProductId(int $productId, bool $onlyActive = false): array;

    public function findByProductAndNumber(int $productId, string $registrationNumber): ?ProductSanitaryRegistration;

    public function save(ProductSanitaryRegistration $registration): ProductSanitaryRegistration;

    public function delete(int $id): void;
}
