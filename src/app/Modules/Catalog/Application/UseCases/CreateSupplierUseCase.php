<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Application\DTOs\SupplierData;
use App\Modules\Catalog\Domain\Entities\Supplier;
use App\Modules\Catalog\Domain\Repositories\SupplierRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;

class CreateSupplierUseCase
{
    public function __construct(
        private readonly SupplierRepositoryInterface $repository,
    ) {}

    public function execute(SupplierData $data): Supplier
    {
        if ($data->taxId !== null && SupplierModel::where('tax_id', $data->taxId)->exists()) {
            throw new \DomainException("Ya existe un proveedor con el NIT/RUT '{$data->taxId}'.");
        }

        $supplier = new Supplier(
            id: null,
            name: $data->name,
            taxId: $data->taxId,
            contactName: $data->contactName,
            phone: $data->phone,
            email: $data->email,
            address: $data->address,
            notes: $data->notes,
        );

        return $this->repository->save($supplier);
    }
}
