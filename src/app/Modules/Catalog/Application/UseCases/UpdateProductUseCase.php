<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Application\DTOs\ProductData;
use App\Modules\Catalog\Domain\Entities\Product;
use App\Modules\Catalog\Domain\Repositories\CategoryRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductClassificationRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\UnitOfMeasureRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;

class UpdateProductUseCase
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly UnitOfMeasureRepositoryInterface $unitOfMeasureRepository,
        private readonly ProductClassificationRepositoryInterface $classificationRepository,
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

        if ($data->classificationId !== null) {
            $classification = $this->classificationRepository->findById($data->classificationId);
            if ($classification === null) {
                throw new \DomainException('La clasificación de producto no existe.');
            }

            if ($classification->hasLabBrand() && empty($data->labBrand)) {
                throw new \DomainException("La clasificación '{$classification->getName()}' requiere el campo laboratorio/marca.");
            }
        }

        $product = new Product(
            id:                     $id,
            categoryId:             $data->categoryId,
            baseUnitId:             $data->baseUnitId,
            productType:            $data->productType,
            name:                   $data->name,
            code:                   $data->code,
            classificationId:       $data->classificationId,
            sku:                    $data->sku,
            description:            $data->description,
            volumeCm3:              $data->volumeCm3,
            weightKg:               $data->weightKg,
            requiresColdChain:      $data->requiresColdChain,
            reorderPoint:           $data->reorderPoint,
            reorderQuantity:        $data->reorderQuantity,
            minStock:               $data->minStock,
            maxStock:               $data->maxStock,
            isActive:               $existing->isActive(),
            concentration:          $data->concentration,
            riskLevel:              $data->riskLevel,
            labBrand:               $data->labBrand,
            pharmaceuticalForm:     $data->pharmaceuticalForm,
            commercialPresentation: $data->commercialPresentation,
            serieReference:         $data->serieReference,
            usefulLife:             $data->usefulLife,
        );

        return $this->repository->save($product);
    }
}
