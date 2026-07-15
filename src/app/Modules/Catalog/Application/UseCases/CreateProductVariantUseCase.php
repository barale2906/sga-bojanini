<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Application\DTOs\ProductVariantData;
use App\Modules\Catalog\Domain\Entities\ProductVariant;
use App\Modules\Catalog\Domain\Repositories\GenericProductRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductClassificationRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductVariantRepositoryInterface;

class CreateProductVariantUseCase
{
    public function __construct(
        private readonly ProductVariantRepositoryInterface $repository,
        private readonly GenericProductRepositoryInterface $genericProductRepository,
        private readonly ProductClassificationRepositoryInterface $classificationRepository,
    ) {}

    public function execute(ProductVariantData $data): ProductVariant
    {
        $generic = $this->genericProductRepository->findById($data->genericProductId);

        if ($generic === null) {
            throw new \DomainException('Producto genérico no encontrado.');
        }

        if ($generic->getClassificationId() !== null) {
            $classification = $this->classificationRepository->findById($generic->getClassificationId());
            if ($classification !== null && $classification->hasLabBrand() && empty($data->labBrand)) {
                throw new \DomainException("La clasificación '{$classification->getName()}' requiere el campo laboratorio/marca.");
            }
        }

        $variant = new ProductVariant(
            id:                     null,
            genericProductId:       $data->genericProductId,
            labBrand:               $data->labBrand,
            brandSku:               $data->brandSku,
            commercialPresentation: $data->commercialPresentation,
            serieReference:         $data->serieReference,
            usefulLife:             $data->usefulLife,
            riskLevel:              $data->riskLevel,
        );

        return $this->repository->save($variant);
    }
}
