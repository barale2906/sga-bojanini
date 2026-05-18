<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Domain\Entities\Supplier;
use App\Modules\Catalog\Domain\Repositories\SupplierRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;

class EloquentSupplierRepository implements SupplierRepositoryInterface
{
    public function findById(int $id): ?Supplier
    {
        $model = SupplierModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(array $filters = []): array
    {
        $query = SupplierModel::query();

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('tax_id', 'like', "%{$filters['search']}%")
                    ->orWhere('contact_name', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('name')->get()->map(fn ($model) => $this->toDomain($model))->toArray();
    }

    public function save(Supplier $supplier): Supplier
    {
        $model = $supplier->getId()
            ? SupplierModel::findOrFail($supplier->getId())
            : new SupplierModel();

        $model->name = $supplier->getName();
        $model->tax_id = $supplier->getTaxId();
        $model->contact_name = $supplier->getContactName();
        $model->phone = $supplier->getPhone();
        $model->email = $supplier->getEmail();
        $model->address = $supplier->getAddress();
        $model->notes = $supplier->getNotes();
        $model->is_active = $supplier->isActive();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        SupplierModel::findOrFail($id)->delete();
    }

    private function toDomain(SupplierModel $model): Supplier
    {
        return new Supplier(
            id: $model->id,
            name: $model->name,
            taxId: $model->tax_id,
            contactName: $model->contact_name,
            phone: $model->phone,
            email: $model->email,
            address: $model->address,
            notes: $model->notes,
            isActive: (bool) $model->is_active,
        );
    }
}
