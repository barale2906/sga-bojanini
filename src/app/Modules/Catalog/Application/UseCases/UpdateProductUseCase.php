<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Application\DTOs\ProductData;
use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Catalog\Domain\Repositories\CategoryRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\UnitOfMeasureRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;

class UpdateProductUseCase
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly UnitOfMeasureRepositoryInterface $unitOfMeasureRepository,
    ) {}

    public function execute(int $id, ProductData $data): Product
    {
        $existing = $this->repository->findById($id);

        if ($existing === null) {
            throw new \DomainException('Producto no encontrado.');
        }

        $duplicate = $this->repository->findByCode($data->code);

        if ($duplicate !== null && $duplicate->getId() !== $id) {
            throw new \DomainException("Ya existe un producto con el código '{$data->code}'.");
        }

        if ($data->sku !== null) {
            $skuDuplicate = ProductModel::where('sku', $data->sku)->where('id', '!=', $id)->exists();

            if ($skuDuplicate) {
                throw new \DomainException("Ya existe un producto con el SKU '{$data->sku}'.");
            }
        }

        if ($this->categoryRepository->findById($data->categoryId) === null) {
            throw new \DomainException('La categoría no existe.');
        }

        if ($this->unitOfMeasureRepository->findById($data->baseUnitId) === null) {
            throw new \DomainException('La unidad de medida base no existe.');
        }

        $product = new Product(
            id: $id,
            categoryId: $data->categoryId,
            baseUnitId: $data->baseUnitId,
            productType: $data->productType,
            name: $data->name,
            code: $data->code,
            sku: $data->sku,
            description: $data->description,
            requiresColdChain: $data->requiresColdChain,
            reorderPoint: $data->reorderPoint,
            reorderQuantity: $data->reorderQuantity,
            minStock: $data->minStock,
            maxStock: $data->maxStock,
            isActive: $existing->isActive(),
        );

        return $this->repository->save($product);
    }
}
