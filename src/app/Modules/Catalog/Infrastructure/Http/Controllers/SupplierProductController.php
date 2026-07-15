<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Infrastructure\Http\Requests\AttachSupplierProductByCategoryRequest;
use App\Modules\Catalog\Infrastructure\Http\Requests\AttachSupplierProductRequest;
use App\Modules\Catalog\Infrastructure\Http\Requests\DetachSupplierProductByCategoryRequest;
use App\Modules\Catalog\Infrastructure\Http\Requests\UpdateSupplierProductRequest;
use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class SupplierProductController extends Controller
{
    use ApiResponse;

    public function index(int $supplierId): JsonResponse
    {
        $supplier = SupplierModel::find($supplierId);

        if ($supplier === null) {
            return $this->error('Proveedor no encontrado', 404);
        }

        $variants = $supplier->products()
            ->with('genericProduct:id,name,barcode,category_id', 'genericProduct.category:id,name,code')
            ->orderBy('lab_brand')
            ->get()
            ->map(fn ($v) => $this->formatVariant($v));

        return $this->success($variants, 'Variantes del proveedor');
    }

    public function suppliersByProduct(int $variantId): JsonResponse
    {
        $variant = ProductVariantModel::find($variantId);

        if ($variant === null) {
            return $this->error('Variante no encontrada', 404);
        }

        $suppliers = $variant->suppliers()
            ->orderBy('name')
            ->get()
            ->map(fn ($s) => $this->formatSupplier($s));

        return $this->success($suppliers, 'Proveedores de la variante');
    }

    public function attach(int $supplierId, int $variantId, AttachSupplierProductRequest $request): JsonResponse
    {
        $supplier = SupplierModel::find($supplierId);
        if ($supplier === null) {
            return $this->error('Proveedor no encontrado', 404);
        }

        if (! ProductVariantModel::where('id', $variantId)->exists()) {
            return $this->error('Variante no encontrada', 404);
        }

        if ($supplier->products()->where('product_variants.id', $variantId)->exists()) {
            return $this->error('La variante ya está asignada a este proveedor', 422);
        }

        $data = $request->validated();

        $supplier->products()->attach($variantId, [
            'supplier_sku'            => $data['supplier_sku'] ?? null,
            'product_presentation_id' => $data['product_presentation_id'] ?? null,
            'lead_time_days'          => $data['lead_time_days'] ?? 0,
            'unit_price'              => $data['unit_price'] ?? 0,
            'is_preferred'            => $data['is_preferred'] ?? false,
        ]);

        $variant = $supplier->products()
            ->with('genericProduct:id,name,barcode,category_id', 'genericProduct.category:id,name,code')
            ->find($variantId);

        return $this->created($this->formatVariant($variant), 'Variante asignada al proveedor');
    }

    public function attachByCategory(int $supplierId, AttachSupplierProductByCategoryRequest $request): JsonResponse
    {
        $supplier = SupplierModel::find($supplierId);
        if ($supplier === null) {
            return $this->error('Proveedor no encontrado', 404);
        }

        $data = $request->validated();
        $category = CategoryModel::find($data['category_id']);

        $variantIds = ProductVariantModel::where('is_active', true)
            ->whereHas('genericProduct', fn ($q) => $q->where('category_id', $data['category_id'])->where('is_active', true))
            ->pluck('id');

        if ($variantIds->isEmpty()) {
            return $this->error('La categoría no tiene variantes activas', 422);
        }

        $alreadyAssigned = $supplier->products()->pluck('product_variants.id');
        $toAttach        = $variantIds->diff($alreadyAssigned);

        if ($toAttach->isEmpty()) {
            return $this->success([
                'assigned' => 0,
                'skipped'  => $alreadyAssigned->intersect($variantIds)->count(),
                'category' => $category->name,
            ], 'Todas las variantes de la categoría ya estaban asignadas');
        }

        $pivotData = [
            'lead_time_days' => $data['lead_time_days'] ?? 0,
            'unit_price'     => $data['unit_price'] ?? 0,
            'is_preferred'   => $data['is_preferred'] ?? false,
        ];

        $supplier->products()->attach(
            $toAttach->mapWithKeys(fn ($id) => [$id => $pivotData])->all()
        );

        return $this->success([
            'assigned' => $toAttach->count(),
            'skipped'  => $alreadyAssigned->intersect($variantIds)->count(),
            'category' => $category->name,
        ], 'Variantes de la categoría asignadas al proveedor');
    }

    public function update(int $supplierId, int $variantId, UpdateSupplierProductRequest $request): JsonResponse
    {
        $supplier = SupplierModel::find($supplierId);
        if ($supplier === null) {
            return $this->error('Proveedor no encontrado', 404);
        }

        if (! $supplier->products()->where('product_variants.id', $variantId)->exists()) {
            return $this->error('La variante no está asignada a este proveedor', 404);
        }

        $supplier->products()->updateExistingPivot($variantId, array_filter(
            $request->validated(),
            fn ($v) => $v !== null
        ));

        $variant = $supplier->products()
            ->with('genericProduct:id,name,barcode,category_id', 'genericProduct.category:id,name,code')
            ->find($variantId);

        return $this->success($this->formatVariant($variant), 'Datos de la variante actualizados');
    }

    public function detach(int $supplierId, int $variantId): JsonResponse
    {
        $supplier = SupplierModel::find($supplierId);
        if ($supplier === null) {
            return $this->error('Proveedor no encontrado', 404);
        }

        if (! $supplier->products()->where('product_variants.id', $variantId)->exists()) {
            return $this->error('La variante no está asignada a este proveedor', 404);
        }

        $supplier->products()->detach($variantId);

        return $this->noContent('Variante eliminada del proveedor');
    }

    public function detachByCategory(int $supplierId, DetachSupplierProductByCategoryRequest $request): JsonResponse
    {
        $supplier = SupplierModel::find($supplierId);
        if ($supplier === null) {
            return $this->error('Proveedor no encontrado', 404);
        }

        $data = $request->validated();
        $category = CategoryModel::find($data['category_id']);

        $variantIds = ProductVariantModel::whereHas('genericProduct', fn ($q) => $q->where('category_id', $data['category_id']))
            ->pluck('id');

        $removed = $supplier->products()
            ->whereIn('product_variants.id', $variantIds)
            ->count();

        $supplier->products()->detach($variantIds);

        return $this->success([
            'removed'  => $removed,
            'category' => $category->name,
        ], 'Variantes de la categoría eliminadas del proveedor');
    }

    private function formatSupplier(mixed $supplier): array
    {
        return [
            'id'           => $supplier->id,
            'name'         => $supplier->name,
            'tax_id'       => $supplier->tax_id,
            'contact_name' => $supplier->contact_name,
            'phone'        => $supplier->phone,
            'email'        => $supplier->email,
            'is_active'    => (bool) $supplier->is_active,
            'pivot' => [
                'supplier_sku'            => $supplier->pivot->supplier_sku,
                'product_presentation_id' => $supplier->pivot->product_presentation_id,
                'lead_time_days'          => (int) $supplier->pivot->lead_time_days,
                'unit_price'              => (float) $supplier->pivot->unit_price,
                'is_preferred'            => (bool) $supplier->pivot->is_preferred,
            ],
        ];
    }

    private function formatVariant(mixed $variant): array
    {
        $generic = $variant->genericProduct;

        return [
            'id'        => $variant->id,
            'lab_brand' => $variant->lab_brand,
            'brand_sku' => $variant->brand_sku,
            'is_active' => (bool) $variant->is_active,
            'generic'   => $generic ? [
                'id'      => $generic->id,
                'name'    => $generic->name,
                'barcode' => $generic->barcode,
                'category' => $generic->relationLoaded('category') && $generic->category ? [
                    'id'   => $generic->category->id,
                    'name' => $generic->category->name,
                    'code' => $generic->category->code,
                ] : null,
            ] : null,
            'pivot' => [
                'supplier_sku'            => $variant->pivot->supplier_sku,
                'product_presentation_id' => $variant->pivot->product_presentation_id,
                'lead_time_days'          => (int) $variant->pivot->lead_time_days,
                'unit_price'              => (float) $variant->pivot->unit_price,
                'is_preferred'            => (bool) $variant->pivot->is_preferred,
            ],
        ];
    }
}
