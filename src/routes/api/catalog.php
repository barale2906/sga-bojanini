<?php

use App\Modules\Catalog\Infrastructure\Http\Controllers\CategoryController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\GenericProductController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\ImportController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\ProductClassificationController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\ProductKitComponentController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\ProductPresentationController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\ProductSanitaryRegistrationController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\ProductVariantController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\SupplierController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\SupplierProductController;
use App\Modules\Catalog\Infrastructure\Http\Controllers\UnitOfMeasureController;
use App\Modules\Inventory\Infrastructure\Http\Controllers\BatchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {

    // Clasificaciones de producto
    Route::apiResource('product-classifications', ProductClassificationController::class)
        ->middleware('permission:generic-products.ver')->only(['index', 'show']);
    Route::apiResource('product-classifications', ProductClassificationController::class)
        ->middleware('permission:generic-products.crear')->only(['store']);
    Route::apiResource('product-classifications', ProductClassificationController::class)
        ->middleware('permission:generic-products.editar')->only(['update']);
    Route::apiResource('product-classifications', ProductClassificationController::class)
        ->middleware('permission:generic-products.eliminar')->only(['destroy']);

    Route::apiResource('categories', CategoryController::class)
        ->middleware('permission:generic-products.ver')->only(['index', 'show']);
    Route::apiResource('categories', CategoryController::class)
        ->middleware('permission:generic-products.crear')->only(['store']);
    Route::apiResource('categories', CategoryController::class)
        ->middleware('permission:generic-products.editar')->only(['update']);
    Route::apiResource('categories', CategoryController::class)
        ->middleware('permission:generic-products.eliminar')->only(['destroy']);

    Route::get('categories-tree', [CategoryController::class, 'tree'])
        ->middleware('permission:generic-products.ver');

    Route::apiResource('units-of-measure', UnitOfMeasureController::class)
        ->middleware('permission:generic-products.ver')->only(['index', 'show']);
    Route::apiResource('units-of-measure', UnitOfMeasureController::class)
        ->middleware('permission:generic-products.crear')->only(['store']);
    Route::apiResource('units-of-measure', UnitOfMeasureController::class)
        ->middleware('permission:generic-products.editar')->only(['update']);
    Route::apiResource('units-of-measure', UnitOfMeasureController::class)
        ->middleware('permission:generic-products.eliminar')->only(['destroy']);

    // Productos genéricos — CRUD
    Route::get('generic-products', [GenericProductController::class, 'index'])
        ->middleware('permission:generic-products.ver');
    Route::post('generic-products', [GenericProductController::class, 'store'])
        ->middleware('permission:generic-products.crear');
    Route::get('generic-products/{id}', [GenericProductController::class, 'show'])
        ->middleware('permission:generic-products.ver');
    Route::put('generic-products/{id}', [GenericProductController::class, 'update'])
        ->middleware('permission:generic-products.editar');
    Route::delete('generic-products/{id}', [GenericProductController::class, 'destroy'])
        ->middleware('permission:generic-products.eliminar');

    // Lotes por producto genérico (todos los variantes, ordenados FEFO)
    Route::get('generic-products/{id}/batches', [BatchController::class, 'byGenericProduct'])
        ->middleware('permission:lotes.ver');

    // Disponibilidad de kit
    Route::get('generic-products/{id}/kit-availability', [GenericProductController::class, 'kitAvailability'])
        ->middleware('permission:stock.ver');

    // Códigos de barras — rutas estáticas ANTES de las dinámicas
    Route::get('generic-products/barcode/{value}', [GenericProductController::class, 'findByBarcode'])
        ->middleware('permission:generic-products.barcode')
        ->where('value', '[0-9]{6}');

    Route::get('generic-products/barcodes/list', [GenericProductController::class, 'barcodeList'])
        ->middleware('permission:generic-products.barcode');

    Route::get('generic-products/{id}/barcode', [GenericProductController::class, 'barcodeSvg'])
        ->middleware('permission:generic-products.barcode');

    Route::get('generic-products/{id}/barcode/print', [GenericProductController::class, 'barcodePrint'])
        ->middleware('permission:generic-products.barcode');

    // Variantes
    Route::get('generic-products/{genericId}/variants', [ProductVariantController::class, 'index'])
        ->middleware('permission:product-variants.ver');
    Route::post('generic-products/{genericId}/variants', [ProductVariantController::class, 'store'])
        ->middleware('permission:product-variants.crear');
    Route::get('generic-products/{genericId}/variants/{variantId}', [ProductVariantController::class, 'show'])
        ->middleware('permission:product-variants.ver');
    Route::put('generic-products/{genericId}/variants/{variantId}', [ProductVariantController::class, 'update'])
        ->middleware('permission:product-variants.editar');
    Route::delete('generic-products/{genericId}/variants/{variantId}', [ProductVariantController::class, 'destroy'])
        ->middleware('permission:product-variants.eliminar');

    // Registros sanitarios por variante
    Route::get('variants/{variantId}/sanitary-registrations', [ProductSanitaryRegistrationController::class, 'index'])
        ->middleware('permission:generic-products.ver');
    Route::post('variants/{variantId}/sanitary-registrations', [ProductSanitaryRegistrationController::class, 'store'])
        ->middleware('permission:generic-products.editar');
    Route::put('variants/{variantId}/sanitary-registrations/{registration}', [ProductSanitaryRegistrationController::class, 'update'])
        ->middleware('permission:generic-products.editar');
    Route::delete('variants/{variantId}/sanitary-registrations/{registration}', [ProductSanitaryRegistrationController::class, 'destroy'])
        ->middleware('permission:generic-products.editar');

    // Proveedor por variante
    Route::get('variants/{variantId}/suppliers', [SupplierProductController::class, 'suppliersByProduct'])
        ->middleware('permission:proveedores.ver');

    // Presentaciones independientes (catálogo)
    Route::get('presentations', [ProductPresentationController::class, 'index'])
        ->middleware('permission:generic-products.ver');
    Route::get('presentations/tree', [ProductPresentationController::class, 'tree'])
        ->middleware('permission:generic-products.ver');
    Route::post('presentations', [ProductPresentationController::class, 'store'])
        ->middleware('permission:generic-products.crear');
    Route::put('presentations/{presentation}', [ProductPresentationController::class, 'update'])
        ->middleware('permission:generic-products.editar');
    Route::delete('presentations/{presentation}', [ProductPresentationController::class, 'destroy'])
        ->middleware('permission:generic-products.eliminar');
    Route::post('presentations/validate-hierarchy', [ProductPresentationController::class, 'validateHierarchy'])
        ->middleware('permission:generic-products.ver');
    Route::post('presentations/convert-to-base', [ProductPresentationController::class, 'convertToBase'])
        ->middleware('permission:generic-products.ver');

    // Presentaciones por genérico (relación M:N)
    Route::get('generic-products/{genericId}/presentations', [ProductPresentationController::class, 'indexByProduct'])
        ->middleware('permission:generic-products.ver');
    Route::get('generic-products/{genericId}/presentations/tree', [ProductPresentationController::class, 'treeByProduct'])
        ->middleware('permission:generic-products.ver');
    Route::post('generic-products/{genericId}/presentations/{presentationId}', [ProductPresentationController::class, 'attach'])
        ->middleware('permission:generic-products.editar');
    Route::delete('generic-products/{genericId}/presentations/{presentationId}', [ProductPresentationController::class, 'detach'])
        ->middleware('permission:generic-products.editar');

    // Kit components por genérico
    Route::get('generic-products/{genericId}/kit-components', [ProductKitComponentController::class, 'index'])
        ->middleware('permission:generic-products.ver');
    Route::put('generic-products/{genericId}/kit-components', [ProductKitComponentController::class, 'sync'])
        ->middleware('permission:generic-products.editar');
    Route::delete('generic-products/{genericId}/kit-components/{componentId}', [ProductKitComponentController::class, 'destroy'])
        ->middleware('permission:generic-products.editar');
    Route::post('generic-products/{genericId}/kit-components/explode', [ProductKitComponentController::class, 'explode'])
        ->middleware('permission:generic-products.ver');

    Route::apiResource('suppliers', SupplierController::class)
        ->middleware('permission:proveedores.ver')->only(['index', 'show']);
    Route::apiResource('suppliers', SupplierController::class)
        ->middleware('permission:proveedores.crear')->only(['store']);
    Route::apiResource('suppliers', SupplierController::class)
        ->middleware('permission:proveedores.editar')->only(['update']);
    Route::apiResource('suppliers', SupplierController::class)
        ->middleware('permission:proveedores.eliminar')->only(['destroy']);

    // Variantes del proveedor
    Route::prefix('suppliers/{supplierId}/variants')->group(function () {
        Route::get('/', [SupplierProductController::class, 'index'])
            ->middleware('permission:proveedores.ver');

        Route::post('by-category', [SupplierProductController::class, 'attachByCategory'])
            ->middleware('permission:proveedores.editar');
        Route::delete('by-category', [SupplierProductController::class, 'detachByCategory'])
            ->middleware('permission:proveedores.editar');

        Route::post('{variantId}', [SupplierProductController::class, 'attach'])
            ->middleware('permission:proveedores.editar');
        Route::put('{variantId}', [SupplierProductController::class, 'update'])
            ->middleware('permission:proveedores.editar');
        Route::delete('{variantId}', [SupplierProductController::class, 'detach'])
            ->middleware('permission:proveedores.editar');
    });

    Route::post('import/products', [ImportController::class, 'importProducts'])
        ->middleware('permission:generic-products.importar');
    Route::post('import/suppliers', [ImportController::class, 'importSuppliers'])
        ->middleware('permission:proveedores.importar');
    Route::get('import/templates/{entity}', [ImportController::class, 'downloadTemplate'])
        ->middleware('permission:generic-products.ver');
});
