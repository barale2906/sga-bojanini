<?php

use App\Modules\Purchasing\Infrastructure\Http\Controllers\ApprovalFlowController;
use App\Modules\Purchasing\Infrastructure\Http\Controllers\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {
    Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])
        ->middleware('permission:purchase_orders.view');
    Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])
        ->middleware('permission:purchase_orders.create');
    Route::get('purchase-orders/suggestions', [PurchaseOrderController::class, 'suggestions'])
        ->middleware('permission:purchase_orders.view');
    Route::get('purchase-orders/{id}', [PurchaseOrderController::class, 'show'])
        ->middleware('permission:purchase_orders.view');
    Route::put('purchase-orders/{id}', [PurchaseOrderController::class, 'update'])
        ->middleware('permission:purchase_orders.create');
    Route::delete('purchase-orders/{id}', [PurchaseOrderController::class, 'destroy'])
        ->middleware('permission:purchase_orders.create');
    Route::post('purchase-orders/{id}/submit', [PurchaseOrderController::class, 'submit'])
        ->middleware('permission:purchase_orders.create');
    Route::post('purchase-orders/{id}/approve', [PurchaseOrderController::class, 'approve'])
        ->middleware('permission:purchase_orders.approve');
    Route::post('purchase-orders/{id}/reject', [PurchaseOrderController::class, 'reject'])
        ->middleware('permission:purchase_orders.approve');
    Route::post('purchase-orders/{id}/send', [PurchaseOrderController::class, 'send'])
        ->middleware('permission:purchase_orders.send');
    Route::post('purchase-orders/{id}/receive', [PurchaseOrderController::class, 'receive'])
        ->middleware('permission:purchase_orders.receive');
    Route::post('purchase-orders/{id}/cancel', [PurchaseOrderController::class, 'cancel'])
        ->middleware('permission:purchase_orders.create');

    Route::get('approval-flows', [ApprovalFlowController::class, 'index'])
        ->middleware('permission:purchase_orders.view');
    Route::post('approval-flows', [ApprovalFlowController::class, 'store'])
        ->middleware('permission:purchase_orders.approve');
    Route::get('approval-flows/{id}', [ApprovalFlowController::class, 'show'])
        ->middleware('permission:purchase_orders.view');
    Route::put('approval-flows/{id}', [ApprovalFlowController::class, 'update'])
        ->middleware('permission:purchase_orders.approve');
    Route::delete('approval-flows/{id}', [ApprovalFlowController::class, 'destroy'])
        ->middleware('permission:purchase_orders.approve');
});
