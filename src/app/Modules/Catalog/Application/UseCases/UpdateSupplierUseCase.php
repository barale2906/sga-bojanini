<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Application\DTOs\SupplierData;
use App\Modules\Catalog\Domain\Entities\Supplier;
use App\Modules\Catalog\Domain\Repositories\SupplierRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;

class UpdateSupplierUseCase
{
    public function __construct(
        private readonly SupplierRepositoryInterface $repository,
    ) {}

    public function execute(int $id, SupplierData $data): Supplier
    {
        $existing = $this->repository->findById($id);

        if ($existing === null) {
            throw new \DomainException('Proveedor no encontrado.');
        }

        if ($data->taxId !== null) {
            $duplicate = SupplierModel::where('tax_id', $data->taxId)->where('id', '!=', $id)->exists();

            if ($duplicate) {
                throw new \DomainException("Ya existe un proveedor con el NIT/RUT '{$data->taxId}'.");
            }
        }

        $supplier = new Supplier(
            id: $id,
            name: $data->name,
            taxId: $data->taxId,
            contactName: $data->contactName,
            phone: $data->phone,
            email: $data->email,
            address: $data->address,
            notes: $data->notes,
            isActive: $existing->isActive(),
        );

        return $this->repository->save($supplier);
    }
}
