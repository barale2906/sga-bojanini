<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Services;

use App\Modules\Catalog\Domain\Repositories\GenericProductRepositoryInterface;
use App\Modules\Catalog\Domain\Repositories\ProductKitComponentRepositoryInterface;

class KitExplosionService
{
    public function __construct(
        private readonly GenericProductRepositoryInterface $genericProductRepository,
        private readonly ProductKitComponentRepositoryInterface $kitComponentRepository,
        private readonly KitRecipeValidator $validator,
    ) {}

    /**
     * @return array<int, array{component_generic_id: int, component_barcode: string, component_name: string, quantity_base: int}>
     */
    public function explode(int $kitGenericId, int $quantityKits): array
    {
        if ($quantityKits < 1) {
            throw new \DomainException('La cantidad de kits debe ser mayor a cero.');
        }

        $kit = $this->genericProductRepository->findById($kitGenericId);

        if ($kit === null || ! $kit->isKit()) {
            throw new \DomainException('Producto kit no válido.');
        }

        $components = $this->kitComponentRepository->findByKitGenericId($kitGenericId);

        if ($components === []) {
            throw new \DomainException('El kit no tiene componentes definidos.');
        }

        $lines = [];

        foreach ($components as $component) {
            $generic = $this->genericProductRepository->findById($component->getComponentGenericId());

            $lines[] = [
                'component_generic_id' => $component->getComponentGenericId(),
                'component_barcode'    => $generic?->getBarcode() ?? '',
                'component_name'       => $generic?->getName() ?? '',
                'quantity_base'        => $component->getQuantityPerKit() * $quantityKits,
            ];
        }

        return $lines;
    }
}
