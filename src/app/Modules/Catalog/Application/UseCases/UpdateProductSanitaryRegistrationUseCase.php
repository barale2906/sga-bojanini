<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Application\DTOs\ProductSanitaryRegistrationData;
use App\Modules\Catalog\Domain\Entities\ProductSanitaryRegistration;
use App\Modules\Catalog\Domain\Repositories\ProductSanitaryRegistrationRepositoryInterface;

class UpdateProductSanitaryRegistrationUseCase
{
    public function __construct(
        private readonly ProductSanitaryRegistrationRepositoryInterface $repository,
    ) {}

    /**
     * @throws \DomainException Si el registro no existe, no pertenece al producto, o el número ya existe.
     */
    public function execute(int $id, ProductSanitaryRegistrationData $data): ProductSanitaryRegistration
    {
        $existing = $this->repository->findById($id);
        if ($existing === null) {
            throw new \DomainException('Registro sanitario no encontrado.');
        }

        if ($existing->getProductVariantId() !== $data->productVariantId) {
            throw new \DomainException('El registro sanitario no pertenece a la variante indicada.');
        }

        $duplicate = $this->repository->findByVariantAndNumber($data->productVariantId, $data->registrationNumber);
        if ($duplicate !== null && $duplicate->getId() !== $id) {
            throw new \DomainException("La variante ya tiene el registro sanitario '{$data->registrationNumber}'.");
        }

        $registration = new ProductSanitaryRegistration(
            id:                 $id,
            productVariantId:   $data->productVariantId,
            registrationNumber: $data->registrationNumber,
            expiryDate:         $data->expiryDate,
            isActive:           $data->isActive,
        );

        return $this->repository->save($registration);
    }
}
