<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\SupplierModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SupplierProductController extends Controller
{
    use ApiResponse;

    /**
     * GET /v1/suppliers/{supplierId}/products
     * Lista todos los productos asignados al proveedor.
     */
    public function index(int $supplierId): JsonResponse
    {
        $supplier = SupplierModel::find($supplierId);

        if ($supplier === null) {
            return $this->error('Proveedor no encontrado', 404);
        }

        $products = $supplier->products()
            ->with('category:id,name,code')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => $this->formatProduct($p));

        return $this->success($products, 'Productos del proveedor');
    }

    /**
     * GET /v1/products/{productId}/suppliers
     * Lista todos los proveedores asignados a un producto.
     */
    public function suppliersByProduct(int $productId): JsonResponse
    {
        $product = ProductModel::find($productId);

        if ($product === null) {
            return $this->error('Producto no encontrado', 404);
        }

        $suppliers = $product->suppliers()
            ->orderBy('name')
            ->get()
            ->map(fn ($s) => $this->formatSupplier($s));

        return $this->success($suppliers, 'Proveedores del producto');
    }

    /**
     * POST /v1/suppliers/{supplierId}/products/{productId}
     * Asigna un producto individual al proveedor.
     *
     * Body (todos opcionales):
     *   supplier_sku          string|null
     *   product_presentation_id int|null
     *   lead_time_days        int  (default 0)
     *   unit_price            numeric (default 0)
     *   is_preferred          bool (default false)
     */
    public function attach(int $supplierId, int $productId, Request $request): JsonResponse
    {
        $supplier = SupplierModel::find($supplierId);
        if ($supplier === null) {
            return $this->error('Proveedor no encontrado', 404);
        }

        if (!ProductModel::where('id', $productId)->exists()) {
            return $this->error('Producto no encontrado', 404);
        }

        if ($supplier->products()->where('product_id', $productId)->exists()) {
            return $this->error('El producto ya está asignado a este proveedor', 422);
        }

        $data = $request->validate([
            'supplier_sku'              => ['nullable', 'string', 'max:100'],
            'product_presentation_id'   => ['nullable', 'integer', 'exists:product_presentations,id'],
            'lead_time_days'            => ['nullable', 'integer', 'min:0'],
            'unit_price'                => ['nullable', 'numeric', 'min:0'],
            'is_preferred'              => ['nullable', 'boolean'],
        ]);

        $supplier->products()->attach($productId, [
            'supplier_sku'            => $data['supplier_sku'] ?? null,
            'product_presentation_id' => $data['product_presentation_id'] ?? null,
            'lead_time_days'          => $data['lead_time_days'] ?? 0,
            'unit_price'              => $data['unit_price'] ?? 0,
            'is_preferred'            => $data['is_preferred'] ?? false,
        ]);

        $product = $supplier->products()->with('category:id,name,code')->find($productId);

        return $this->created($this->formatProduct($product), 'Producto asignado al proveedor');
    }

    /**
     * POST /v1/suppliers/{supplierId}/products/by-category
     * Asigna todos los productos activos de una categoría al proveedor.
     * Omite los que ya estén asignados.
     *
     * Body:
     *   category_id           int  (requerido)
     *   lead_time_days        int  (default 0, aplica a todos)
     *   unit_price            numeric (default 0, aplica a todos)
     *   is_preferred          bool (default false, aplica a todos)
     */
    public function attachByCategory(int $supplierId, Request $request): JsonResponse
    {
        $supplier = SupplierModel::find($supplierId);
        if ($supplier === null) {
            return $this->error('Proveedor no encontrado', 404);
        }

        $data = $request->validate([
            'category_id'    => ['required', 'integer', 'exists:categories,id'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'unit_price'     => ['nullable', 'numeric', 'min:0'],
            'is_preferred'   => ['nullable', 'boolean'],
        ]);

        $category = CategoryModel::find($data['category_id']);

        $products = ProductModel::where('category_id', $data['category_id'])
            ->where('is_active', true)
            ->pluck('id');

        if ($products->isEmpty()) {
            return $this->error('La categoría no tiene productos activos', 422);
        }

        $alreadyAssigned = $supplier->products()->pluck('product_id');
        $toAttach = $products->diff($alreadyAssigned);

        if ($toAttach->isEmpty()) {
            return $this->success([
                'assigned'   => 0,
                'skipped'    => $alreadyAssigned->intersect($products)->count(),
                'category'   => $category->name,
            ], 'Todos los productos de la categoría ya estaban asignados');
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
            'skipped'  => $alreadyAssigned->intersect($products)->count(),
            'category' => $category->name,
        ], 'Productos de la categoría asignados al proveedor');
    }

    /**
     * PUT /v1/suppliers/{supplierId}/products/{productId}
     * Actualiza los datos del pivot para un producto ya asignado.
     */
    public function update(int $supplierId, int $productId, Request $request): JsonResponse
    {
        $supplier = SupplierModel::find($supplierId);
        if ($supplier === null) {
            return $this->error('Proveedor no encontrado', 404);
        }

        if (!$supplier->products()->where('product_id', $productId)->exists()) {
            return $this->error('El producto no está asignado a este proveedor', 404);
        }

        $data = $request->validate([
            'supplier_sku'            => ['nullable', 'string', 'max:100'],
            'product_presentation_id' => ['nullable', 'integer', 'exists:product_presentations,id'],
            'lead_time_days'          => ['nullable', 'integer', 'min:0'],
            'unit_price'              => ['nullable', 'numeric', 'min:0'],
            'is_preferred'            => ['nullable', 'boolean'],
        ]);

        $supplier->products()->updateExistingPivot($productId, array_filter(
            $data,
            fn ($v) => $v !== null
        ));

        $product = $supplier->products()->with('category:id,name,code')->find($productId);

        return $this->success($this->formatProduct($product), 'Datos del producto actualizados');
    }

    /**
     * DELETE /v1/suppliers/{supplierId}/products/{productId}
     * Elimina la asignación de un producto al proveedor.
     */
    public function detach(int $supplierId, int $productId): JsonResponse
    {
        $supplier = SupplierModel::find($supplierId);
        if ($supplier === null) {
            return $this->error('Proveedor no encontrado', 404);
        }

        if (!$supplier->products()->where('product_id', $productId)->exists()) {
            return $this->error('El producto no está asignado a este proveedor', 404);
        }

        $supplier->products()->detach($productId);

        return $this->noContent('Producto eliminado del proveedor');
    }

    /**
     * DELETE /v1/suppliers/{supplierId}/products/by-category
     * Elimina todos los productos de una categoría asignados al proveedor.
     *
     * Body:
     *   category_id  int (requerido)
     */
    public function detachByCategory(int $supplierId, Request $request): JsonResponse
    {
        $supplier = SupplierModel::find($supplierId);
        if ($supplier === null) {
            return $this->error('Proveedor no encontrado', 404);
        }

        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ]);

        $category = CategoryModel::find($data['category_id']);

        $productIds = ProductModel::where('category_id', $data['category_id'])
            ->pluck('id');

        $removed = $supplier->products()
            ->whereIn('product_id', $productIds)
            ->count();

        $supplier->products()->detach($productIds);

        return $this->success([
            'removed'  => $removed,
            'category' => $category->name,
        ], 'Productos de la categoría eliminados del proveedor');
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

    private function formatProduct(mixed $product): array
    {
        return [
            'id'          => $product->id,
            'name'        => $product->name,
            'code'        => $product->code,
            'sku'         => $product->sku,
            'is_active'   => (bool) $product->is_active,
            'category'    => $product->relationLoaded('category') && $product->category ? [
                'id'   => $product->category->id,
                'name' => $product->category->name,
                'code' => $product->category->code,
            ] : null,
            'pivot' => [
                'supplier_sku'            => $product->pivot->supplier_sku,
                'product_presentation_id' => $product->pivot->product_presentation_id,
                'lead_time_days'          => (int) $product->pivot->lead_time_days,
                'unit_price'              => (float) $product->pivot->unit_price,
                'is_preferred'            => (bool) $product->pivot->is_preferred,
            ],
        ];
    }
}
