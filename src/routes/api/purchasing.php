<?php

use App\Modules\Purchasing\Infrastructure\Http\Controllers\ApprovalFlowController;
use App\Modules\Purchasing\Infrastructure\Http\Controllers\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {
    Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])
        ->middleware('permission:ordenes_compra.ver');
    Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])
        ->middleware('permission:ordenes_compra.crear');
    Route::get('purchase-orders/suggestions', [PurchaseOrderController::class, 'suggestions'])
        ->middleware('permission:ordenes_compra.ver');
    Route::get('purchase-orders/{id}', [PurchaseOrderController::class, 'show'])
        ->middleware('permission:ordenes_compra.ver');
    Route::put('purchase-orders/{id}', [PurchaseOrderController::class, 'update'])
        ->middleware('permission:ordenes_compra.crear');
    Route::delete('purchase-orders/{id}', [PurchaseOrderController::class, 'destroy'])
        ->middleware('permission:ordenes_compra.crear');
    Route::post('purchase-orders/{id}/submit', [PurchaseOrderController::class, 'submit'])
        ->middleware('permission:ordenes_compra.crear');
    Route::post('purchase-orders/{id}/approve', [PurchaseOrderController::class, 'approve'])
        ->middleware('permission:ordenes_compra.aprobar');
    Route::post('purchase-orders/{id}/reject', [PurchaseOrderController::class, 'reject'])
        ->middleware('permission:ordenes_compra.aprobar');
    Route::post('purchase-orders/{id}/send', [PurchaseOrderController::class, 'send'])
        ->middleware('permission:ordenes_compra.enviar');
    Route::post('purchase-orders/{id}/receive', [PurchaseOrderController::class, 'receive'])
        ->middleware('permission:ordenes_compra.recibir');
    Route::post('purchase-orders/{id}/cancel', [PurchaseOrderController::class, 'cancel'])
        ->middleware('permission:ordenes_compra.crear');

    Route::get('approval-flows', [ApprovalFlowController::class, 'index'])
        ->middleware('permission:ordenes_compra.ver');
    Route::post('approval-flows', [ApprovalFlowController::class, 'store'])
        ->middleware('permission:ordenes_compra.aprobar');
    Route::get('approval-flows/{id}', [ApprovalFlowController::class, 'show'])
        ->middleware('permission:ordenes_compra.ver');
    Route::put('approval-flows/{id}', [ApprovalFlowController::class, 'update'])
        ->middleware('permission:ordenes_compra.aprobar');
    Route::delete('approval-flows/{id}', [ApprovalFlowController::class, 'destroy'])
        ->middleware('permission:ordenes_compra.aprobar');
});
