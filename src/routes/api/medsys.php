<?php

use App\Modules\Integration\Infrastructure\Http\Controllers\MedsysPatientController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'user.is_active'])->prefix('v1')->group(function () {
    // Los pacientes solo existen en MedSys (la app no los crea): quien registra
    // procedimientos o genera órdenes de servicio debe poder buscarlos aunque
    // no tenga `integraciones.ver` (permiso de administración de integraciones).
    $patientLookup = 'permission.any:integraciones.ver,registros_procedimientos.crear,ordenes_servicio.crear';

    // Búsqueda de pacientes — por documento (exacto) o por nombre (parcial)
    Route::get('medsys/patients', [MedsysPatientController::class, 'search'])
        ->middleware($patientLookup);

    // Citas activas de un paciente
    Route::get('medsys/patients/{codigo}/appointments', [MedsysPatientController::class, 'appointments'])
        ->middleware($patientLookup);

    // Tipos de procedimiento MedSys con su mapeo a servicios SGA
    Route::get('medsys/procedure-types', [MedsysPatientController::class, 'procedureTypes'])
        ->middleware('permission:integraciones.ver');

    // Proyección de consumo basada en citas futuras
    Route::get('medsys/consumption-projection', [MedsysPatientController::class, 'consumptionProjection'])
        ->middleware('permission:integraciones.ver');
});
