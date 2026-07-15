<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Application\DTOs\GenericProductData;
use App\Modules\Catalog\Domain\Entities\GenericProduct;
use App\Modules\Catalog\Domain\Entities\ProductVariant;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Repositories\CategoryRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\GenericProductRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductClassificationRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductVariantRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\UnitOfMeasureRepositoryInterface;
use App\Modules\Catalog\Domain\Services\BarcodeValueGenerator;

class CreateGenericProductUseCase
{
    public function __construct(
        private readonly GenericProductRepositoryInterface $repository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly UnitOfMeasureRepositoryInterface $unitOfMeasureRepository,
        private readonly ProductClassificationRepositoryInterface $classificationRepository,
        private readonly BarcodeValueGenerator $barcodeGenerator,
        private readonly SyncKitComponentsUseCase $syncKitComponents,
        private readonly ProductVariantRepositoryInterface $variantRepository,
    ) {}

    /**
     * @param array<int, array{component_generic_id: int, quantity_per_kit: int}>|null $components
     */
    public function execute(GenericProductData $data, ?array $components = null): GenericProduct
    {
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
        }

        $barcode = $this->barcodeGenerator->generate($this->repository->getMaxBarcode());

        $product = new GenericProduct(
            id:                null,
            categoryId:        $data->categoryId,
            baseUnitId:        $data->baseUnitId,
            productType:       $data->productType,
            name:              $data->name,
            barcode:           $barcode,
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
        );

        $saved = $this->repository->save($product);

        if ($data->productType === ProductType::Kit) {
            if ($components !== null) {
                $this->syncKitComponents->execute((int) $saved->getId(), $components);
            }
        } else {
            $this->variantRepository->save(new ProductVariant(
                id:              null,
                genericProductId: (int) $saved->getId(),
            ));
        }

        return $saved;
    }
}
