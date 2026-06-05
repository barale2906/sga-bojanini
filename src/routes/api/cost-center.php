<?php

use App\Modules\CostCenter\Infrastructure\Http\Controllers\CostCenterController;
use App\Modules\CostCenter\Infrastructure\Http\Controllers\MedicalServiceController;
use App\Modules\CostCenter\Infrastructure\Http\Controllers\PatientProcedureRecordController;
use App\Modules\CostCenter\Infrastructure\Http\Controllers\ProcedurePriceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {

    // ─── Centros de Costo ────────────────────────────────────────────────────────
    Route::apiResource('cost-centers', CostCenterController::class)
        ->middleware('permission:centros_costo.ver')->only(['index', 'show']);
    Route::apiResource('cost-centers', CostCenterController::class)
        ->middleware('permission:centros_costo.crear')->only(['store']);
    Route::apiResource('cost-centers', CostCenterController::class)
        ->middleware('permission:centros_costo.editar')->only(['update']);
    Route::apiResource('cost-centers', CostCenterController::class)
        ->middleware('permission:centros_costo.eliminar')->only(['destroy']);

    // ─── Servicios Médicos y Procedimientos ──────────────────────────────────────
    Route::middleware('permission:servicios_medicos.ver')->group(function () {
        Route::get('medical-services/tree', [MedicalServiceController::class, 'tree'])
            ->name('medical-services.tree');
        Route::apiResource('medical-services', MedicalServiceController::class)->only(['index', 'show']);
        Route::get('medical-services/{medical_service}/procedures', [MedicalServiceController::class, 'procedures'])
            ->name('medical-services.procedures');
    });
    Route::apiResource('medical-services', MedicalServiceController::class)
        ->middleware('permission:servicios_medicos.crear')->only(['store']);
    Route::apiResource('medical-services', MedicalServiceController::class)
        ->middleware('permission:servicios_medicos.editar')->only(['update']);
    Route::apiResource('medical-services', MedicalServiceController::class)
        ->middleware('permission:servicios_medicos.eliminar')->only(['destroy']);

    // ─── Tarifas de Procedimientos ───────────────────────────────────────────────
    Route::middleware('permission:procedimientos.ver')->group(function () {
        Route::get('procedures/{procedure}/prices', [ProcedurePriceController::class, 'index'])
            ->name('procedures.prices.index');
        Route::get('procedures/{procedure}/prices/{price}', [ProcedurePriceController::class, 'show'])
            ->name('procedures.prices.show');
    });
    Route::post('procedures/{procedure}/prices', [ProcedurePriceController::class, 'store'])
        ->middleware('permission:procedimientos.crear')
        ->name('procedures.prices.store');
    Route::put('procedures/{procedure}/prices/{price}', [ProcedurePriceController::class, 'update'])
        ->middleware('permission:procedimientos.editar')
        ->name('procedures.prices.update');
    Route::delete('procedures/{procedure}/prices/{price}', [ProcedurePriceController::class, 'destroy'])
        ->middleware('permission:procedimientos.eliminar')
        ->name('procedures.prices.destroy');

    // ─── Registros de Procedimientos por Paciente ────────────────────────────────
    // La ruta de historial se registra ANTES del apiResource para evitar que Laravel
    // confunda "by-patient" con un ID de recurso en la ruta /{patient_procedure_record}.
    Route::get(
        'patients/{patientExternalId}/procedure-records',
        [PatientProcedureRecordController::class, 'history'],
    )
        ->middleware('permission:registros_procedimientos.ver')
        ->name('patients.procedure-records.history');

    Route::apiResource('patient-procedure-records', PatientProcedureRecordController::class)
        ->middleware('permission:registros_procedimientos.ver')->only(['index', 'show']);
    Route::apiResource('patient-procedure-records', PatientProcedureRecordController::class)
        ->middleware('permission:registros_procedimientos.crear')->only(['store']);
    Route::apiResource('patient-procedure-records', PatientProcedureRecordController::class)
        ->middleware('permission:registros_procedimientos.editar')->only(['update']);
    Route::apiResource('patient-procedure-records', PatientProcedureRecordController::class)
        ->middleware('permission:registros_procedimientos.eliminar')->only(['destroy']);
});
