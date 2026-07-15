<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Application\DTOs\ProductSanitaryRegistrationData;
use App\Modules\Catalog\Domain\Entities\ProductSanitaryRegistration;
use App\Modules\Catalog\Domain\Repositories\ProductSanitaryRegistrationRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductVariantRepositoryInterface;

class CreateProductSanitaryRegistrationUseCase
{
    public function __construct(
        private readonly ProductSanitaryRegistrationRepositoryInterface $repository,
        private readonly ProductVariantRepositoryInterface $variantRepository,
    ) {}

    /**
     * @throws \DomainException Si la variante no existe o el número de registro ya está registrado.
     */
    public function execute(ProductSanitaryRegistrationData $data): ProductSanitaryRegistration
    {
        if ($this->variantRepository->findById($data->productVariantId) === null) {
            throw new \DomainException('Variante de producto no encontrada.');
        }

        if ($this->repository->findByVariantAndNumber($data->productVariantId, $data->registrationNumber) !== null) {
            throw new \DomainException("La variante ya tiene el registro sanitario '{$data->registrationNumber}'.");
        }

        $registration = new ProductSanitaryRegistration(
            id:                 null,
            productVariantId:   $data->productVariantId,
            registrationNumber: $data->registrationNumber,
            expiryDate:         $data->expiryDate,
            isActive:           $data->isActive,
        );

        return $this->repository->save($registration);
    }
}
