<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Application\DTOs\SupplierData;
use App\Modules\Catalog\Application\UseCases\CreateSupplierUseCase;
use App\Modules\Catalog\Application\UseCases\DeleteSupplierUseCase;
use App\Modules\Catalog\Application\UseCases\UpdateSupplierUseCase;
use App\Modules\Catalog\Domain\Repositories\SupplierRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Http\Requests\StoreSupplierRequest;
use App\Modules\Catalog\Infrastructure\Http\Requests\UpdateSupplierRequest;
use App\Modules\Catalog\Infrastructure\Http\Resources\SupplierResource;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SupplierController extends Controller
{
    use ApiResponse;

    public function index(Request $request, SupplierRepositoryInterface $repository): JsonResponse
    {
        $suppliers = $repository->findAll([
            'is_active' => $request->query('is_active'),
            'search'    => $request->query('search'),
            'type'      => $request->query('type'),
        ]);

        return $this->success(
            SupplierResource::collection($suppliers),
            'Listado de proveedores'
        );
    }

    public function search(Request $request, SupplierRepositoryInterface $repository): JsonResponse
    {
        $q    = (string) $request->query('q', '');
        $type = $request->query('type');

        $filters = ['is_active' => true];

        if (strlen($q) >= 2) {
            $filters['search'] = $q;
        }

        if ($type !== null) {
            $filters['type'] = $type;
        }

        $suppliers = $repository->findAll($filters);

        $results = array_map(fn ($s) => [
            'id'            => $s->getId(),
            'name'          => $s->getName(),
            'tax_id'        => $s->getTaxId(),
            'email'         => $s->getEmail(),
            'supplier_type' => $s->getSupplierType(),
        ], array_slice($suppliers, 0, 20));

        return $this->success($results, 'Búsqueda de proveedores');
    }

    public function store(StoreSupplierRequest $request, CreateSupplierUseCase $useCase): JsonResponse
    {
        $data = new SupplierData(
            name: $request->validated('name'),
            taxId: $request->validated('tax_id'),
            contactName: $request->validated('contact_name'),
            phone: $request->validated('phone'),
            email: $request->validated('email'),
            address: $request->validated('address'),
            notes: $request->validated('notes'),
            supplierType: $request->validated('supplier_type') ?? 'both',
        );

        $supplier = $useCase->execute($data);

        return $this->created(
            new SupplierResource($supplier),
            'Proveedor creado exitosamente'
        );
    }

    public function show(int $supplier, SupplierRepositoryInterface $repository): JsonResponse
    {
        $entity = $repository->findById($supplier);

        if ($entity === null) {
            return $this->error('Proveedor no encontrado', 404);
        }

        return $this->success(
            new SupplierResource($entity),
            'Detalle del proveedor'
        );
    }

    public function update(int $supplier, UpdateSupplierRequest $request, UpdateSupplierUseCase $useCase): JsonResponse
    {
        $data = new SupplierData(
            name: $request->validated('name'),
            taxId: $request->validated('tax_id'),
            contactName: $request->validated('contact_name'),
            phone: $request->validated('phone'),
            email: $request->validated('email'),
            address: $request->validated('address'),
            notes: $request->validated('notes'),
            supplierType: $request->validated('supplier_type') ?? 'both',
        );

        $entity = $useCase->execute($supplier, $data);

        return $this->success(
            new SupplierResource($entity),
            'Proveedor actualizado exitosamente'
        );
    }

    public function destroy(int $supplier, DeleteSupplierUseCase $useCase): JsonResponse
    {
        $useCase->execute($supplier);

        return $this->noContent('Proveedor eliminado');
    }
}
