<?php

use App\Modules\CostCenter\Infrastructure\Http\Controllers\PriceListController;
use App\Modules\CostCenter\Infrastructure\Http\Controllers\ServiceOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {
    Route::post('service-orders', [ServiceOrderController::class, 'store'])
        ->middleware('permission:ordenes_servicio.crear');

    Route::get('service-orders/discounts', [ServiceOrderController::class, 'discounts'])
        ->middleware('permission:ordenes_servicio.aprobar');

    Route::get('service-orders/{orderNumber}', [ServiceOrderController::class, 'show'])
        ->middleware('permission:ordenes_servicio.ver');

    Route::post('service-orders/{orderNumber}/approve', [ServiceOrderController::class, 'approve'])
        ->middleware('permission:ordenes_servicio.aprobar');

    Route::post('service-orders/{orderNumber}/bill', [ServiceOrderController::class, 'bill'])
        ->middleware('permission:ordenes_servicio.ver');

    Route::post('service-orders/{orderNumber}/cancel', [ServiceOrderController::class, 'cancel'])
        ->middleware('permission:ordenes_servicio.ver');

    Route::get('procedures/{id}/current-price', [PriceListController::class, 'currentPrice'])
        ->middleware('permission:ordenes_servicio.ver');

    Route::get('price-lists/template', [PriceListController::class, 'template'])
        ->middleware('permission:listas_precios.ver');

    Route::post('price-lists/import', [PriceListController::class, 'import'])
        ->middleware('permission:listas_precios.crear');
});
