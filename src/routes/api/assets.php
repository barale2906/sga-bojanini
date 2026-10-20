<?php

use App\Modules\Assets\Infrastructure\Http\Controllers\AssetCategoryController;
use App\Modules\Assets\Infrastructure\Http\Controllers\AssetController;
use App\Modules\Assets\Infrastructure\Http\Controllers\AssetDocumentController;
use App\Modules\Assets\Infrastructure\Http\Controllers\AssetImportController;
use App\Modules\Assets\Infrastructure\Http\Controllers\AssetMaintenanceController;
use App\Modules\Assets\Infrastructure\Http\Controllers\AssetMaintenancePlanController;
use App\Modules\Assets\Infrastructure\Http\Controllers\AssetReportController;
use App\Modules\Assets\Infrastructure\Http\Controllers\AssetSiteController;
use App\Modules\Assets\Infrastructure\Http\Controllers\AssetTechnicalProfileController;
use App\Modules\Assets\Infrastructure\Http\Controllers\AssetTransferController;
use Illuminate\Support\Facades\Route;

/*
| Módulo de Activos: inventario de equipos biomédicos, de cómputo y bienes
| generales; ubicaciones (sede → sitio), traslados con firma, hoja de vida,
| cronograma de mantenimiento, documentos e importación masiva.
| Guía frontend: docs/frontend-activos.md
*/
Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1/assets')->name('assets.')->group(function () {

    // ─── Catálogos: categorías, sedes y sitios ──────────────────────────────────
    Route::middleware('permission:activos.ver')->group(function () {
        Route::get('categories', [AssetCategoryController::class, 'index'])->name('categories.index');
        Route::get('sites', [AssetSiteController::class, 'index'])->name('sites.index');
    });
    Route::middleware('permission:activos_catalogos.crear')->group(function () {
        Route::post('categories', [AssetCategoryController::class, 'store'])->name('categories.store');
        Route::post('sites', [AssetSiteController::class, 'store'])->name('sites.store');
        Route::post('places', [AssetSiteController::class, 'storePlace'])->name('places.store');
    });
    Route::middleware('permission:activos_catalogos.editar')->group(function () {
        Route::put('categories/{id}', [AssetCategoryController::class, 'update'])->name('categories.update');
        Route::put('sites/{id}', [AssetSiteController::class, 'update'])->name('sites.update');
        Route::put('places/{id}', [AssetSiteController::class, 'updatePlace'])->name('places.update');
    });
    Route::middleware('permission:activos_catalogos.eliminar')->group(function () {
        Route::delete('categories/{id}', [AssetCategoryController::class, 'destroy'])->name('categories.destroy');
        Route::delete('sites/{id}', [AssetSiteController::class, 'destroy'])->name('sites.destroy');
        Route::delete('places/{id}', [AssetSiteController::class, 'destroyPlace'])->name('places.destroy');
    });

    // ─── Importación masiva ─────────────────────────────────────────────────────
    Route::middleware('permission:activos.importar')->group(function () {
        Route::get('import/template', [AssetImportController::class, 'template'])->name('import.template');
        Route::post('import', [AssetImportController::class, 'import'])->name('import');
    });

    // ─── Reportes y etiquetas ───────────────────────────────────────────────────
    Route::middleware('permission:activos.exportar')->group(function () {
        Route::get('reports/inventory', [AssetReportController::class, 'inventory'])->name('reports.inventory');
        Route::get('reports/maintenance-schedule', [AssetReportController::class, 'maintenanceSchedule'])->name('reports.maintenance-schedule');
    });
    Route::get('labels', [AssetReportController::class, 'labels'])
        ->middleware('permission:activos.ver')->name('labels');

    // ─── Traslados ──────────────────────────────────────────────────────────────
    Route::middleware('permission:activos.ver')->group(function () {
        Route::get('transfers', [AssetTransferController::class, 'index'])->name('transfers.index');
        Route::get('transfers/{id}', [AssetTransferController::class, 'show'])->whereNumber('id')->name('transfers.show');
        Route::get('transfers/{id}/act', [AssetTransferController::class, 'act'])->whereNumber('id')->name('transfers.act');
    });
    Route::post('{assetId}/transfers', [AssetTransferController::class, 'store'])
        ->whereNumber('assetId')->middleware('permission:activos_traslados.solicitar')->name('transfers.store');
    Route::middleware('permission:activos_traslados.confirmar')->group(function () {
        Route::post('transfers/{id}/confirm', [AssetTransferController::class, 'confirm'])->whereNumber('id')->name('transfers.confirm');
        Route::post('transfers/{id}/cancel', [AssetTransferController::class, 'cancel'])->whereNumber('id')->name('transfers.cancel');
    });

    // ─── Mantenimientos (cronograma y órdenes) ──────────────────────────────────
    Route::middleware('permission:activos_mantenimientos.ver')->group(function () {
        Route::get('maintenances', [AssetMaintenanceController::class, 'index'])->name('maintenances.index');
        Route::get('maintenances/{id}', [AssetMaintenanceController::class, 'show'])->whereNumber('id')->name('maintenances.show');
        Route::get('maintenances/{id}/report', [AssetMaintenanceController::class, 'report'])->whereNumber('id')->name('maintenances.report');
        Route::get('{assetId}/maintenance-plans', [AssetMaintenancePlanController::class, 'index'])->whereNumber('assetId')->name('plans.index');
    });
    Route::middleware('permission:activos_mantenimientos.programar')->group(function () {
        Route::post('{assetId}/maintenance-plans', [AssetMaintenancePlanController::class, 'store'])->whereNumber('assetId')->name('plans.store');
        Route::put('maintenance-plans/{id}', [AssetMaintenancePlanController::class, 'update'])->whereNumber('id')->name('plans.update');
        Route::delete('maintenance-plans/{id}', [AssetMaintenancePlanController::class, 'destroy'])->whereNumber('id')->name('plans.destroy');
        Route::post('{assetId}/maintenances', [AssetMaintenanceController::class, 'store'])->whereNumber('assetId')->name('maintenances.store');
        Route::put('maintenances/{id}', [AssetMaintenanceController::class, 'reschedule'])->whereNumber('id')->name('maintenances.reschedule');
    });
    Route::middleware('permission:activos_mantenimientos.ejecutar')->group(function () {
        Route::post('maintenances/{id}/start', [AssetMaintenanceController::class, 'start'])->whereNumber('id')->name('maintenances.start');
        Route::post('maintenances/{id}/complete', [AssetMaintenanceController::class, 'complete'])->whereNumber('id')->name('maintenances.complete');
    });
    Route::post('maintenances/{id}/cancel', [AssetMaintenanceController::class, 'cancel'])
        ->whereNumber('id')->middleware('permission:activos_mantenimientos.cancelar')->name('maintenances.cancel');

    // ─── Documentos y soportes ──────────────────────────────────────────────────
    Route::middleware('permission:activos.ver')->group(function () {
        Route::get('{assetId}/documents', [AssetDocumentController::class, 'index'])->whereNumber('assetId')->name('documents.index');
        Route::get('documents/{id}/download', [AssetDocumentController::class, 'download'])->whereNumber('id')->name('documents.download');
    });
    Route::post('{assetId}/documents', [AssetDocumentController::class, 'store'])
        ->whereNumber('assetId')->middleware('permission:activos_documentos.cargar')->name('documents.store');
    Route::delete('documents/{id}', [AssetDocumentController::class, 'destroy'])
        ->whereNumber('id')->middleware('permission:activos_documentos.eliminar')->name('documents.destroy');

    // ─── Activos ────────────────────────────────────────────────────────────────
    Route::middleware('permission:activos.ver')->group(function () {
        Route::get('/', [AssetController::class, 'index'])->name('index');
        Route::get('filters', [AssetController::class, 'filters'])->name('filters');
        Route::get('statistics', [AssetController::class, 'statistics'])->name('statistics');
        Route::get('{id}', [AssetController::class, 'show'])->whereNumber('id')->name('show');
        Route::get('{id}/movements', [AssetController::class, 'movements'])->whereNumber('id')->name('movements');
        Route::get('{id}/location-history', [AssetController::class, 'locationHistory'])->whereNumber('id')->name('location-history');
        Route::get('{id}/comments', [AssetController::class, 'comments'])->whereNumber('id')->name('comments.index');
        Route::get('{assetId}/technical-profile', [AssetTechnicalProfileController::class, 'show'])->whereNumber('assetId')->name('profile.show');
        Route::get('{assetId}/life-sheet', [AssetTechnicalProfileController::class, 'pdf'])->whereNumber('assetId')->name('life-sheet');
    });
    Route::post('{id}/comments', [AssetController::class, 'storeComment'])
        ->whereNumber('id')->middleware('permission:activos.comentar')->name('comments.store');
    Route::post('/', [AssetController::class, 'store'])->middleware('permission:activos.crear')->name('store');
    Route::middleware('permission:activos.editar')->group(function () {
        Route::put('{id}', [AssetController::class, 'update'])->whereNumber('id')->name('update');
        Route::post('{id}/status', [AssetController::class, 'changeStatus'])->whereNumber('id')->name('status');
        Route::put('{assetId}/technical-profile', [AssetTechnicalProfileController::class, 'update'])->whereNumber('assetId')->name('profile.update');
    });
    Route::delete('{id}', [AssetController::class, 'destroy'])
        ->whereNumber('id')->middleware('permission:activos.eliminar')->name('destroy');
});
