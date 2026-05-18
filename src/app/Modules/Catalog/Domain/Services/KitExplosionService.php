<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Services;

use App\Modules\Catalog\Domain\Repositories\ProductKitComponentRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductRepositoryInterface;

class KitExplosionService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductKitComponentRepositoryInterface $kitComponentRepository,
        private readonly KitRecipeValidator $validator,
    ) {}

    /**
     * @return array<int, array{component_product_id: int, component_code: string, component_name: string, quantity_base: int}>
     */
    public function explode(int $kitProductId, int $quantityKits): array
    {
        if ($quantityKits < 1) {
            throw new \DomainException('La cantidad de kits debe ser mayor a cero.');
        }

        $kit = $this->productRepository->findById($kitProductId);

        if ($kit === null || ! $kit->isKit()) {
            throw new \DomainException('Producto kit no válido.');
        }

        $components = $this->kitComponentRepository->findByKitProductId($kitProductId);

        if ($components === []) {
            throw new \DomainException('El kit no tiene componentes definidos.');
        }

        $lines = [];

        foreach ($components as $component) {
            $product = $this->productRepository->findById($component->getComponentProductId());

            $lines[] = [
                'component_product_id' => $component->getComponentProductId(),
                'component_code'       => $product?->getCode() ?? '',
                'component_name'       => $product?->getName() ?? '',
                'quantity_base'        => $component->getQuantityPerKit() * $quantityKits,
            ];
        }

        return $lines;
    }
}
