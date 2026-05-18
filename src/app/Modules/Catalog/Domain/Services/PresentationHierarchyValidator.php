<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Services;

use App\Modules\Catalog\Domain\Repositories\ProductPresentationRepositoryInterface;

class PresentationHierarchyValidator
{
    public function __construct(
        private readonly ProductPresentationRepositoryInterface $presentationRepository,
    ) {}

    /**
     * @param array{product_id: int, parent_id?: int|null, factor_to_base: int, quantity_per_parent?: int|null, level: int} $data
     */
    public function validate(array $data, ?int $excludeId = null): void
    {
        if ($data['factor_to_base'] < 1) {
            throw new \DomainException('factor_to_base debe ser mayor a cero.');
        }

        if ($data['level'] < 1) {
            throw new \DomainException('level debe ser al menos 1.');
        }

        $parentId = $data['parent_id'] ?? null;

        if ($parentId === null && $data['level'] !== 1) {
            throw new \DomainException('Las presentaciones raíz deben tener level = 1.');
        }

        if ($parentId !== null) {
            $parent = $this->presentationRepository->findById($parentId);

            if ($parent === null) {
                throw new \DomainException('La presentación padre no existe.');
            }

            if ($parent->getProductId() !== $data['product_id']) {
                throw new \DomainException('La presentación padre debe pertenecer al mismo producto.');
            }

            if ($parent->getLevel() + 1 !== $data['level']) {
                throw new \DomainException('El level debe ser padre.level + 1.');
            }

            $qty = $data['quantity_per_parent'] ?? null;

            if ($qty === null || $qty < 1) {
                throw new \DomainException('quantity_per_parent es obligatorio cuando hay padre.');
            }

            $expectedFactor = $parent->getFactorToBase() * $qty;

            if ($data['factor_to_base'] !== $expectedFactor) {
                throw new \DomainException(
                    "factor_to_base debe ser {$expectedFactor} (padre × quantity_per_parent)."
                );
            }
        }
    }
}
