<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Services;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Repositories\ProductRepositoryInterface;

class KitRecipeValidator
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {}

    /**
     * @param array<int, array{component_product_id: int, quantity_per_kit: int}> $components
     */
    public function validate(int $kitProductId, array $components): void
    {
        $kit = $this->productRepository->findById($kitProductId);

        if ($kit === null) {
            throw new \DomainException('El producto kit no existe.');
        }

        if (! $kit->isKit()) {
            throw new \DomainException('El producto no es de tipo kit.');
        }

        if ($components === []) {
            throw new \DomainException('Un kit debe tener al menos un componente.');
        }

        $seen = [];

        foreach ($components as $index => $row) {
            $componentId = (int) $row['component_product_id'];

            if ($componentId === $kitProductId) {
                throw new \DomainException('Un kit no puede incluirse a sí mismo como componente.');
            }

            if (isset($seen[$componentId])) {
                throw new \DomainException('Componente duplicado en la receta del kit.');
            }

            $seen[$componentId] = true;

            $component = $this->productRepository->findById($componentId);

            if ($component === null) {
                throw new \DomainException("Componente en posición {$index} no existe.");
            }

            if ($component->isKit()) {
                throw new \DomainException('Los componentes de un kit deben ser productos simples.');
            }

            if ((int) $row['quantity_per_kit'] < 1) {
                throw new \DomainException('La cantidad por kit debe ser al menos 1.');
            }
        }
    }
}
