<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Application\DTOs\ProductVariantData;
use App\Modules\Catalog\Domain\Entities\ProductVariant;
use App\Modules\Catalog\Domain\Repositories\ProductVariantRepositoryInterface;

class UpdateProductVariantUseCase
{
    public function __construct(
        private readonly ProductVariantRepositoryInterface $repository,
    ) {}

    public function execute(int $id, ProductVariantData $data): ProductVariant
    {
        $existing = $this->repository->findById($id);

        if ($existing === null) {
            throw new \DomainException('Variante de producto no encontrada.');
        }

        if ($existing->getGenericProductId() !== $data->genericProductId) {
            throw new \DomainException('La variante no pertenece al producto genérico indicado.');
        }

        $variant = new ProductVariant(
            id:                     $id,
            genericProductId:       $data->genericProductId,
            labBrand:               $data->labBrand,
            brandSku:               $data->brandSku,
            commercialPresentation: $data->commercialPresentation,
            serieReference:         $data->serieReference,
            usefulLife:             $data->usefulLife,
            riskLevel:              $data->riskLevel,
            isActive:               $existing->isActive(),
        );

        return $this->repository->save($variant);
    }
}
