<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Application\DTOs\ProductSanitaryRegistrationData;
use App\Modules\Catalog\Domain\Entities\ProductSanitaryRegistration;
use App\Modules\Catalog\Domain\Repositories\ProductRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductSanitaryRegistrationRepositoryInterface;

class CreateProductSanitaryRegistrationUseCase
{
    public function __construct(
        private readonly ProductSanitaryRegistrationRepositoryInterface $repository,
        private readonly ProductRepositoryInterface $productRepository,
    ) {}

    /**
     * @throws \DomainException Si el producto no existe o el número de registro ya está registrado.
     */
    public function execute(ProductSanitaryRegistrationData $data): ProductSanitaryRegistration
    {
        if ($this->productRepository->findById($data->productId) === null) {
            throw new \DomainException('Producto no encontrado.');
        }

        if ($this->repository->findByProductAndNumber($data->productId, $data->registrationNumber) !== null) {
            throw new \DomainException("El producto ya tiene el registro sanitario '{$data->registrationNumber}'.");
        }

        $registration = new ProductSanitaryRegistration(
            id:                 null,
            productId:          $data->productId,
            registrationNumber: $data->registrationNumber,
            expiryDate:         $data->expiryDate,
            isActive:           $data->isActive,
        );

        return $this->repository->save($registration);
    }
}
