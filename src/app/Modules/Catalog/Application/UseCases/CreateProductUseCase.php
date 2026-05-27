<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Application\DTOs\ProductData;
use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Catalog\Domain\Repositories\CategoryRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\UnitOfMeasureRepositoryInterface;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;

class CreateProductUseCase
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly UnitOfMeasureRepositoryInterface $unitOfMeasureRepository,
        private readonly SyncKitComponentsUseCase $syncKitComponents,
    ) {}

    /**
     * @param array<int, array{component_product_id: int, quantity_per_kit: int}>|null $components
     */
    public function execute(ProductData $data, ?array $components = null): Product
    {
        if ($this->repository->findByCode($data->code) !== null) {
            throw new \DomainException("Ya existe un producto con el código '{$data->code}'.");
        }

        if ($data->sku !== null && ProductModel::where('sku', $data->sku)->exists()) {
            throw new \DomainException("Ya existe un producto con el SKU '{$data->sku}'.");
        }

        if ($this->categoryRepository->findById($data->categoryId) === null) {
            throw new \DomainException('La categoría no existe.');
        }

        if ($this->unitOfMeasureRepository->findById($data->baseUnitId) === null) {
            throw new \DomainException('La unidad de medida base no existe.');
        }

        $product = new Product(
            id:                null,
            categoryId:        $data->categoryId,
            baseUnitId:        $data->baseUnitId,
            productType:       $data->productType,
            name:              $data->name,
            code:              $data->code,
            sku:               $data->sku,
            description:       $data->description,
            volumeCm3:         $data->volumeCm3,
            weightKg:          $data->weightKg,
            requiresColdChain: $data->requiresColdChain,
            reorderPoint:      $data->reorderPoint,
            reorderQuantity:   $data->reorderQuantity,
            minStock:          $data->minStock,
            maxStock:          $data->maxStock,
        );

        $saved = $this->repository->save($product);

        if ($data->productType === ProductType::Kit && $components !== null) {
            $this->syncKitComponents->execute((int) $saved->getId(), $components);
        }

        return $saved;
    }
}
