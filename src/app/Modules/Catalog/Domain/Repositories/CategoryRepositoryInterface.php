<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Repositories;

use App\Modules\Catalog\Domain\Entities\Category;

interface CategoryRepositoryInterface
{
    public function findById(int $id): ?Category;

    public function findByCode(string $code): ?Category;

    public function findAll(array $filters = []): array;

    public function save(Category $category): Category;

    public function delete(int $id): void;
}
