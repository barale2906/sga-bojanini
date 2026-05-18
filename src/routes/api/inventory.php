<?php

use App\Modules\Inventory\Infrastructure\Http\Controllers\BatchController;
use App\Modules\Inventory\Infrastructure\Http\Controllers\MovementController;
use App\Modules\Inventory\Infrastructure\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {
    Route::get('batches', [BatchController::class, 'index'])->middleware('permission:batches.view');
    Route::get('batches/expiring', [BatchController::class, 'expiring'])->middleware('permission:batches.view');
    Route::get('batches/expired', [BatchController::class, 'expired'])->middleware('permission:batches.view');
    Route::get('batches/{id}', [BatchController::class, 'show'])->middleware('permission:batches.view');
    Route::get('products/{id}/batches', [BatchController::class, 'byProduct'])->middleware('permission:batches.view');

    Route::get('stock', [StockController::class, 'index'])->middleware('permission:stock.view');
    Route::get('stock/summary', [StockController::class, 'summary'])->middleware('permission:stock.view');
    Route::get('stock/low', [StockController::class, 'low'])->middleware('permission:stock.view');

    Route::post('movements/entry', [MovementController::class, 'entry'])->middleware('permission:movements.entry');
    Route::post('movements/exit', [MovementController::class, 'exit'])->middleware('permission:movements.exit');
    Route::post('movements/transfer', [MovementController::class, 'transfer'])->middleware('permission:movements.transfer');
    Route::post('movements/adjustment', [MovementController::class, 'adjustment'])->middleware('permission:movements.adjustment');
    Route::post('movements/return', [MovementController::class, 'returnStock'])->middleware('permission:movements.return');
    Route::post('movements/write-off', [MovementController::class, 'writeOff'])->middleware('permission:movements.write_off');
    Route::get('movements', [MovementController::class, 'index'])->middleware('permission:stock.view');
    Route::get('movements/{id}', [MovementController::class, 'show'])->middleware('permission:stock.view');
});
