<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Application\DTOs\CategoryData;
use App\Modules\Catalog\Domain\Entities\Category;
use App\Modules\Catalog\Domain\Repositories\CategoryRepositoryInterface;

class CreateCategoryUseCase
{
    public function __construct(
        private readonly CategoryRepositoryInterface $repository,
    ) {}

    public function execute(CategoryData $data): Category
    {
        if ($this->repository->findByCode($data->code) !== null) {
            throw new \DomainException("Ya existe una categoría con el código '{$data->code}'.");
        }

        if ($data->parentId !== null && $this->repository->findById($data->parentId) === null) {
            throw new \DomainException('La categoría padre no existe.');
        }

        $category = new Category(
            id: null,
            parentId: $data->parentId,
            name: $data->name,
            code: $data->code,
            description: $data->description,
        );

        return $this->repository->save($category);
    }
}
