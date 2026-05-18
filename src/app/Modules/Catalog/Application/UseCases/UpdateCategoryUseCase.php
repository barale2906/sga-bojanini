<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Application\DTOs\CategoryData;
use App\Modules\Catalog\Domain\Entities\Category;
use App\Modules\Catalog\Domain\Repositories\CategoryRepositoryInterface;

class UpdateCategoryUseCase
{
    public function __construct(
        private readonly CategoryRepositoryInterface $repository,
    ) {}

    public function execute(int $id, CategoryData $data): Category
    {
        $existing = $this->repository->findById($id);

        if ($existing === null) {
            throw new \DomainException('Categoría no encontrada.');
        }

        $duplicate = $this->repository->findByCode($data->code);

        if ($duplicate !== null && $duplicate->getId() !== $id) {
            throw new \DomainException("Ya existe una categoría con el código '{$data->code}'.");
        }

        if ($data->parentId === $id) {
            throw new \DomainException('Una categoría no puede ser padre de sí misma.');
        }

        if ($data->parentId !== null && $this->repository->findById($data->parentId) === null) {
            throw new \DomainException('La categoría padre no existe.');
        }

        $category = new Category(
            id: $id,
            parentId: $data->parentId,
            name: $data->name,
            code: $data->code,
            description: $data->description,
            isActive: $existing->isActive(),
        );

        return $this->repository->save($category);
    }
}
