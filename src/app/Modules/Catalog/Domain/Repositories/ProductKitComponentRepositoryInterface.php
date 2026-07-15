<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Repositories;

use App\Modules\Catalog\Domain\Entities\ProductKitComponent;

interface ProductKitComponentRepositoryInterface
{
    /** @return ProductKitComponent[] */
    public function findByKitGenericId(int $kitGenericId, bool $activeOnly = true): array;

    /**
     * Returns kit components as plain arrays including component generic product details.
     * Use this for read-model / API responses.
     *
     * @return array<int, array{
     *     id: int,
     *     kit_generic_id: int,
     *     component_generic_id: int,
     *     quantity_per_kit: int,
     *     sort_order: int,
     *     notes: string|null,
     *     is_active: bool,
     *     component: array{id: int, name: string, barcode: string|null}|null
     * }>
     */
    public function findWithDetailsByKitGenericId(int $kitGenericId, bool $activeOnly = false): array;

    public function save(ProductKitComponent $component): ProductKitComponent;

    /**
     * Deletes a single component by its own ID, only if it belongs to the given kit.
     * Returns true when deleted, false when not found or kit mismatch.
     */
    public function deleteById(int $componentId, int $kitGenericId): bool;

    public function deleteByKitGenericId(int $kitGenericId): void;
}
