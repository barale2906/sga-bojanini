<?php

use App\Modules\Inventory\Infrastructure\Http\Controllers\BatchController;
use App\Modules\Inventory\Infrastructure\Http\Controllers\MovementController;
use App\Modules\Inventory\Infrastructure\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {
    Route::get('batches', [BatchController::class, 'index'])->middleware('permission:lotes.ver');
    Route::get('batches/expiring', [BatchController::class, 'expiring'])->middleware('permission:lotes.ver');
    Route::get('batches/expired', [BatchController::class, 'expired'])->middleware('permission:lotes.ver');
    Route::get('batches/{id}', [BatchController::class, 'show'])->middleware('permission:lotes.ver');
    Route::get('products/{id}/batches', [BatchController::class, 'byProduct'])->middleware('permission:lotes.ver');

    Route::get('stock', [StockController::class, 'index'])->middleware('permission:stock.ver');
    Route::get('stock/summary', [StockController::class, 'summary'])->middleware('permission:stock.ver');
    Route::get('stock/kit-availability', [StockController::class, 'kitAvailability'])->middleware('permission:stock.ver');
    Route::get('stock/low', [StockController::class, 'low'])->middleware('permission:stock.ver');

    Route::get('movements/initial-entries/template', [MovementController::class, 'downloadInitialEntriesTemplate'])->middleware('permission:stock.ver');
    Route::post('movements/initial-entries/import', [MovementController::class, 'importInitialEntries'])->middleware('permission:movimientos.importar');

    Route::post('movements/entry', [MovementController::class, 'entry'])->middleware('permission:movimientos.entrada');
    Route::post('movements/exit', [MovementController::class, 'exit'])->middleware('permission:movimientos.salida');
    Route::post('movements/transfer', [MovementController::class, 'transfer'])->middleware('permission:movimientos.transferir');
    Route::post('movements/adjustment', [MovementController::class, 'adjustment'])->middleware('permission:movimientos.ajuste');
    Route::post('movements/return', [MovementController::class, 'returnStock'])->middleware('permission:movimientos.devolucion');
    Route::post('movements/write-off', [MovementController::class, 'writeOff'])->middleware('permission:movimientos.baja');
    Route::post('movements/loss', [MovementController::class, 'loss'])->middleware('permission:movimientos.baja');
    Route::get('movements', [MovementController::class, 'index'])->middleware('permission:stock.ver');
    Route::get('movements/{id}', [MovementController::class, 'show'])->middleware('permission:stock.ver');
});
