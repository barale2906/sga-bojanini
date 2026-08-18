<?php

use App\Modules\Purchasing\Infrastructure\Http\Controllers\ApprovalFlowController;
use App\Modules\Purchasing\Infrastructure\Http\Controllers\ConsolidatedPurchaseOrderController;
use App\Modules\Purchasing\Infrastructure\Http\Controllers\ExpenseOrderController;
use App\Modules\Purchasing\Infrastructure\Http\Controllers\PurchaseOrderController;
use App\Modules\Purchasing\Infrastructure\Http\Controllers\PurchaseOrderPaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {
    Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])
        ->middleware('permission:ordenes_compra.ver');
    Route::post('purchase-orders', [PurchaseOrderController::class, 'store'])
        ->middleware('permission:ordenes_compra.crear');
    Route::get('purchase-orders/suggestions', [PurchaseOrderController::class, 'suggestions'])
        ->middleware('permission:ordenes_compra.ver');
    Route::get('purchase-orders/consolidable-suppliers', [ConsolidatedPurchaseOrderController::class, 'consolidableSuppliers'])
        ->middleware('permission:ordenes_compra.ver');
    Route::get('purchase-orders/consolidable', [ConsolidatedPurchaseOrderController::class, 'consolidable'])
        ->middleware('permission:ordenes_compra.ver');
    Route::post('purchase-orders/consolidation-preview', [ConsolidatedPurchaseOrderController::class, 'preview'])
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

    Route::get('consolidated-orders', [ConsolidatedPurchaseOrderController::class, 'index'])
        ->middleware('permission:ordenes_compra.ver');
    Route::post('consolidated-orders', [ConsolidatedPurchaseOrderController::class, 'store'])
        ->middleware('permission:ordenes_compra.consolidar');
    Route::get('consolidated-orders/{id}', [ConsolidatedPurchaseOrderController::class, 'show'])
        ->middleware('permission:ordenes_compra.ver');

    // Expense orders (órdenes de compra de gastos)
    Route::post('expense-orders/supplier', [ExpenseOrderController::class, 'storeSupplier'])
        ->middleware('permission:ordenes_gasto.crear');
    Route::get('expense-orders', [ExpenseOrderController::class, 'index'])
        ->middleware('permission:ordenes_gasto.ver');
    Route::post('expense-orders', [ExpenseOrderController::class, 'store'])
        ->middleware('permission:ordenes_gasto.crear');
    Route::get('expense-orders/{id}', [ExpenseOrderController::class, 'show'])
        ->middleware('permission:ordenes_gasto.ver');
    Route::put('expense-orders/{id}', [ExpenseOrderController::class, 'update'])
        ->middleware('permission:ordenes_gasto.crear');
    Route::delete('expense-orders/{id}', [ExpenseOrderController::class, 'destroy'])
        ->middleware('permission:ordenes_gasto.crear');
    Route::post('expense-orders/{id}/submit', [ExpenseOrderController::class, 'submit'])
        ->middleware('permission:ordenes_gasto.crear');
    Route::post('expense-orders/{id}/approve', [ExpenseOrderController::class, 'approve'])
        ->middleware('permission:ordenes_gasto.aprobar');
    Route::post('expense-orders/{id}/reject', [ExpenseOrderController::class, 'reject'])
        ->middleware('permission:ordenes_gasto.aprobar');
    Route::post('expense-orders/{id}/send', [ExpenseOrderController::class, 'send'])
        ->middleware('permission:ordenes_gasto.enviar');
    Route::post('expense-orders/{id}/receive', [ExpenseOrderController::class, 'receive'])
        ->middleware('permission:ordenes_gasto.recibir');
    Route::post('expense-orders/{id}/cancel', [ExpenseOrderController::class, 'cancel'])
        ->middleware('permission:ordenes_gasto.crear');
    Route::post('expense-orders/{id}/invoice', [ExpenseOrderController::class, 'invoice'])
        ->middleware('permission:ordenes_gasto.factura');
    Route::post('expense-orders/{id}/send-accounting', [ExpenseOrderController::class, 'sendAccounting'])
        ->middleware('permission:ordenes_gasto.factura');

    // Payments for expense orders
    Route::get('expense-orders/{orderId}/payments', [PurchaseOrderPaymentController::class, 'index'])
        ->middleware('permission:ordenes_gasto.ver');
    Route::post('expense-orders/{orderId}/payments', [PurchaseOrderPaymentController::class, 'store'])
        ->middleware('permission:ordenes_gasto.pagos');
    Route::delete('expense-orders/{orderId}/payments/{paymentId}', [PurchaseOrderPaymentController::class, 'destroy'])
        ->middleware('permission:ordenes_gasto.pagos');

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
