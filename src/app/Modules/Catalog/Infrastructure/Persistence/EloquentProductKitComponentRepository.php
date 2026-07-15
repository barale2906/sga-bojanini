<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Domain\Entities\ProductKitComponent;
use App\Modules\Catalog\Domain\Repositories\ProductKitComponentRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductKitComponentModel;

class EloquentProductKitComponentRepository implements ProductKitComponentRepositoryInterface
{
    public function findByKitGenericId(int $kitGenericId, bool $activeOnly = true): array
    {
        $query = ProductKitComponentModel::where('kit_generic_id', $kitGenericId);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('sort_order')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->toArray();
    }

    public function findWithDetailsByKitGenericId(int $kitGenericId, bool $activeOnly = false): array
    {
        $query = ProductKitComponentModel::with('componentGeneric')
            ->where('kit_generic_id', $kitGenericId);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('sort_order')
            ->get()
            ->map(fn ($m) => [
                'id'                   => $m->id,
                'kit_generic_id'       => $m->kit_generic_id,
                'component_generic_id' => $m->component_generic_id,
                'quantity_per_kit'     => $m->quantity_per_kit,
                'sort_order'           => (int) $m->sort_order,
                'notes'                => $m->notes,
                'is_active'            => (bool) $m->is_active,
                'component'            => $m->componentGeneric ? [
                    'id'      => $m->componentGeneric->id,
                    'name'    => $m->componentGeneric->name,
                    'barcode' => $m->componentGeneric->barcode,
                ] : null,
            ])
            ->toArray();
    }

    public function save(ProductKitComponent $component): ProductKitComponent
    {
        $model = $component->getId()
            ? ProductKitComponentModel::findOrFail($component->getId())
            : new ProductKitComponentModel();

        $model->kit_generic_id       = $component->getKitGenericId();
        $model->component_generic_id = $component->getComponentGenericId();
        $model->quantity_per_kit     = $component->getQuantityPerKit();
        $model->sort_order           = $component->getSortOrder();
        $model->notes                = $component->getNotes();
        $model->is_active            = $component->isActive();
        $model->save();

        return $this->toDomain($model);
    }

    public function deleteById(int $componentId, int $kitGenericId): bool
    {
        $deleted = ProductKitComponentModel::where('id', $componentId)
            ->where('kit_generic_id', $kitGenericId)
            ->delete();

        return $deleted > 0;
    }

    public function deleteByKitGenericId(int $kitGenericId): void
    {
        ProductKitComponentModel::where('kit_generic_id', $kitGenericId)->delete();
    }

    private function toDomain(ProductKitComponentModel $model): ProductKitComponent
    {
        return new ProductKitComponent(
            id:                 $model->id,
            kitGenericId:       $model->kit_generic_id,
            componentGenericId: $model->component_generic_id,
            quantityPerKit:     $model->quantity_per_kit,
            sortOrder:          (int) $model->sort_order,
            notes:              $model->notes,
            isActive:           (bool) $model->is_active,
        );
    }
}
