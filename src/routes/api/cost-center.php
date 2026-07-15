<?php

use App\Modules\CostCenter\Infrastructure\Http\Controllers\ClinicalTemplateController;
use App\Modules\CostCenter\Infrastructure\Http\Controllers\CostCenterController;
use App\Modules\CostCenter\Infrastructure\Http\Controllers\ImportController;
use App\Modules\CostCenter\Infrastructure\Http\Controllers\MedicalServiceController;
use App\Modules\CostCenter\Infrastructure\Http\Controllers\PatientClinicalEvolutionController;
use App\Modules\CostCenter\Infrastructure\Http\Controllers\PatientMedicationRecordController;
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

    // ─── Importación masiva de Servicios y Procedimientos ────────────────────────
    Route::post('import/medical-services', [ImportController::class, 'importMedicalServices'])
        ->middleware('permission:servicios_medicos.importar');
    Route::get('import/medical-services/template', [ImportController::class, 'downloadTemplate'])
        ->middleware('permission:servicios_medicos.importar');

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

    // ─── Plantillas de Evolución Clínica ─────────────────────────────────────────
    Route::middleware('permission:plantillas_clinicas.ver')->group(function () {
        Route::get('clinical-templates/for-service/{medicalServiceId}', [ClinicalTemplateController::class, 'forService'])
            ->name('clinical-templates.for-service');
        Route::apiResource('clinical-templates', ClinicalTemplateController::class)->only(['index', 'show']);
    });
    Route::apiResource('clinical-templates', ClinicalTemplateController::class)
        ->middleware('permission:plantillas_clinicas.crear')->only(['store']);
    Route::apiResource('clinical-templates', ClinicalTemplateController::class)
        ->middleware('permission:plantillas_clinicas.editar')->only(['update']);
    Route::apiResource('clinical-templates', ClinicalTemplateController::class)
        ->middleware('permission:plantillas_clinicas.eliminar')->only(['destroy']);

    // ─── Evoluciones Clínicas por Registro de Procedimiento ──────────────────────
    Route::middleware('permission:evoluciones_clinicas.ver')->group(function () {
        Route::get(
            'patient-procedure-records/{patientProcedureRecord}/evolutions',
            [PatientClinicalEvolutionController::class, 'index'],
        )->name('procedure-records.evolutions.index');
    });
    Route::post(
        'patient-procedure-records/{patientProcedureRecord}/evolutions',
        [PatientClinicalEvolutionController::class, 'store'],
    )->middleware('permission:evoluciones_clinicas.crear')
     ->name('procedure-records.evolutions.store');
    Route::put(
        'patient-procedure-records/{patientProcedureRecord}/evolutions/{evolution}',
        [PatientClinicalEvolutionController::class, 'update'],
    )->middleware('permission:evoluciones_clinicas.editar')
     ->name('procedure-records.evolutions.update');
    Route::delete(
        'patient-procedure-records/{patientProcedureRecord}/evolutions/{evolution}',
        [PatientClinicalEvolutionController::class, 'destroy'],
    )->middleware('permission:evoluciones_clinicas.eliminar')
     ->name('procedure-records.evolutions.destroy');

    // ─── Medicamentos por Registro de Procedimiento (lectura desde movimiento) ────
    Route::get(
        'patient-procedure-records/{patientProcedureRecord}/medications',
        [PatientMedicationRecordController::class, 'index'],
    )->middleware('permission:medicamentos_procedimiento.ver')
     ->name('procedure-records.medications.index');
});
