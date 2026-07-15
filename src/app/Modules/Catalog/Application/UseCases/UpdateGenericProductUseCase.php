<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Application\DTOs\GenericProductData;
use App\Modules\Catalog\Domain\Entities\GenericProduct;
use App\Modules\Catalog\Domain\Repositories\CategoryRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\GenericProductRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductClassificationRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\UnitOfMeasureRepositoryInterface;

class UpdateGenericProductUseCase
{
    public function __construct(
        private readonly GenericProductRepositoryInterface $repository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly UnitOfMeasureRepositoryInterface $unitOfMeasureRepository,
        private readonly ProductClassificationRepositoryInterface $classificationRepository,
    ) {}

    public function execute(int $id, GenericProductData $data): GenericProduct
    {
        $existing = $this->repository->findById($id);

        if ($existing === null) {
            throw new \DomainException('Producto genérico no encontrado.');
        }

        if ($this->categoryRepository->findById($data->categoryId) === null) {
            throw new \DomainException('La categoría no existe.');
        }

        if ($this->unitOfMeasureRepository->findById($data->baseUnitId) === null) {
            throw new \DomainException('La unidad de medida base no existe.');
        }

        if ($data->classificationId !== null) {
            if ($this->classificationRepository->findById($data->classificationId) === null) {
                throw new \DomainException('La clasificación de producto no existe.');
            }
        }

        $product = new GenericProduct(
            id:                $id,
            categoryId:        $data->categoryId,
            baseUnitId:        $data->baseUnitId,
            productType:       $data->productType,
            name:              $data->name,
            barcode:           $existing->getBarcode(),
            classificationId:  $data->classificationId,
            description:       $data->description,
            concentration:     $data->concentration,
            pharmaceuticalForm: $data->pharmaceuticalForm,
            volumeCm3:         $data->volumeCm3,
            weightKg:          $data->weightKg,
            requiresColdChain: $data->requiresColdChain,
            reorderPoint:      $data->reorderPoint,
            reorderQuantity:   $data->reorderQuantity,
            minStock:          $data->minStock,
            maxStock:          $data->maxStock,
            isActive:          $existing->isActive(),
        );

        return $this->repository->save($product);
    }
}
