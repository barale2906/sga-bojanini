<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Controllers;

use App\Modules\CostCenter\Application\DTOs\PatientProcedureRecordData;
use App\Modules\CostCenter\Application\UseCases\CreatePatientProcedureRecordUseCase;
use App\Modules\CostCenter\Application\UseCases\DeletePatientProcedureRecordUseCase;
use App\Modules\CostCenter\Application\UseCases\GetPatientProcedureHistoryUseCase;
use App\Modules\CostCenter\Application\UseCases\ListPatientProcedureRecordsUseCase;
use App\Modules\CostCenter\Application\UseCases\UpdatePatientProcedureRecordUseCase;
use App\Modules\CostCenter\Infrastructure\Http\Requests\StorePatientProcedureRecordRequest;
use App\Modules\CostCenter\Infrastructure\Http\Requests\UpdatePatientProcedureRecordRequest;
use App\Modules\CostCenter\Infrastructure\Http\Resources\PatientProcedureHistoryResource;
use App\Modules\CostCenter\Infrastructure\Http\Resources\PatientProcedureRecordResource;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\PatientProcedureRecordModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * @group Registros de Procedimientos por Paciente
 *
 * Registro de procedimientos médicos realizados a pacientes con su
 * cantidad, valor unitario y total calculado.
 * Solo aplica a procedimientos (type=procedure en medical_services).
 */
class PatientProcedureRecordController extends Controller
{
    use ApiResponse;

    /**
     * Listar registros de procedimientos.
     *
     * @queryParam medical_service_id integer Filtrar por procedimiento. Example: 2
     * @queryParam patient_external_id string Filtrar por ID de paciente externo. Example: PAC-001
     * @queryParam patient_document string Buscar por documento del paciente. Example: 12345678
     * @queryParam service_date_from string Fecha de atención desde (Y-m-d). Example: 2026-01-01
     * @queryParam service_date_to string Fecha de atención hasta (Y-m-d). Example: 2026-12-31
     * @queryParam is_active boolean Filtrar por estado. Example: true
     *
     * @response 200 {"success": true, "data": [{"id":1,"patient_document":"12345678","quantity":2,...}]}
     */
    public function index(Request $request, ListPatientProcedureRecordsUseCase $useCase): JsonResponse
    {
        $filters = $request->only([
            'medical_service_id',
            'patient_external_id',
            'patient_document',
            'service_date_from',
            'service_date_to',
            'is_active',
        ]);

        $items = $useCase->execute($filters);

        return $this->success(
            PatientProcedureRecordResource::collection($items),
            'Registros de procedimientos',
        );
    }

    /**
     * Obtener un registro.
     *
     * @urlParam id integer required ID del registro. Example: 1
     *
     * @response 200 {"success": true, "data": {"id":1,"quantity":2,"unit_price":50000,"total":100000,...}}
     * @response 404 {"success": false, "message": "Registro no encontrado."}
     */
    public function show(int $id): JsonResponse
    {
        $model = PatientProcedureRecordModel::find($id);

        if ($model === null) {
            return $this->error('Registro de procedimiento no encontrado', 404);
        }

        return $this->success(new PatientProcedureRecordResource($model), 'Detalle del registro');
    }

    /**
     * Crear un registro de procedimiento para un paciente.
     *
     * El total se calcula automáticamente: quantity * unit_price.
     *
     * @bodyParam medical_service_id integer required ID del procedimiento (type=procedure). Example: 2
     * @bodyParam patient_external_id string required ID del paciente en sistema externo. Example: PAC-001
     * @bodyParam patient_document string required Documento del paciente. Example: 12345678
     * @bodyParam quantity number required Cantidad (> 0). Example: 2
     * @bodyParam unit_price number required Precio unitario (>= 0). Example: 50000
     * @bodyParam service_date string required Fecha de atención Y-m-d (no futura). Example: 2026-06-01
     * @bodyParam notes string Notas opcionales.
     *
     * @response 201 {"success": true, "data": {"id":1,"total":100000,...}}
     * @response 409 {"success": false, "message": "El registro ... es un servicio, no un procedimiento."}
     * @response 422 {"success": false, "errors": {...}}
     */
    public function store(StorePatientProcedureRecordRequest $request, CreatePatientProcedureRecordUseCase $useCase): JsonResponse
    {
        $validated = $request->validated();

        $record = $useCase->execute(new PatientProcedureRecordData(
            medicalServiceId:  (int) $validated['medical_service_id'],
            patientExternalId: $validated['patient_external_id'],
            patientDocument:   $validated['patient_document'],
            quantity:          (float) $validated['quantity'],
            unitPrice:         (float) $validated['unit_price'],
            serviceDate:       new DateTimeImmutable($validated['service_date']),
            notes:             $validated['notes'] ?? null,
        ));

        return $this->created(new PatientProcedureRecordResource($record), 'Registro creado exitosamente');
    }

    /**
     * Actualizar un registro de procedimiento.
     *
     * @urlParam patient_procedure_record integer required ID del registro. Example: 1
     *
     * @response 200 {"success": true, "data": {...}}
     * @response 409 {"success": false, "message": "Registro de procedimiento con id 99 no encontrado."}
     */
    public function update(UpdatePatientProcedureRecordRequest $request, int $patient_procedure_record, UpdatePatientProcedureRecordUseCase $useCase): JsonResponse
    {
        $validated = $request->validated();

        $record = $useCase->execute($patient_procedure_record, new PatientProcedureRecordData(
            medicalServiceId:  (int) $validated['medical_service_id'],
            patientExternalId: $validated['patient_external_id'],
            patientDocument:   $validated['patient_document'],
            quantity:          (float) $validated['quantity'],
            unitPrice:         (float) $validated['unit_price'],
            serviceDate:       new DateTimeImmutable($validated['service_date']),
            notes:             $validated['notes'] ?? null,
            isActive:          (bool) ($validated['is_active'] ?? true),
        ));

        return $this->success(new PatientProcedureRecordResource($record), 'Registro actualizado exitosamente');
    }

    /**
     * Eliminar un registro (soft delete).
     *
     * @urlParam patient_procedure_record integer required ID del registro. Example: 1
     *
     * @response 200 {"success": true, "message": "Registro eliminado"}
     * @response 409 {"success": false, "message": "Registro de procedimiento con id 99 no encontrado."}
     */
    public function destroy(int $patient_procedure_record, DeletePatientProcedureRecordUseCase $useCase): JsonResponse
    {
        $useCase->execute($patient_procedure_record);

        return $this->noContent('Registro eliminado');
    }

    /**
     * Historial de procedimientos de un paciente.
     *
     * Retorna todos los procedimientos ejecutados al paciente identificado por su ID externo,
     * enriquecidos con el nombre del procedimiento y del servicio padre, más un resumen agregado.
     *
     * @urlParam patientExternalId string required ID del paciente en el sistema externo. Example: PAC-001
     * @queryParam service_date_from string Fecha de atención desde (Y-m-d). Example: 2026-01-01
     * @queryParam service_date_to string Fecha de atención hasta (Y-m-d). Example: 2026-12-31
     * @queryParam medical_service_id integer Filtrar por procedimiento. Example: 3
     * @queryParam is_active boolean Filtrar por estado. Example: true
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Historial del paciente PAC-001",
     *   "data": {
     *     "patient_external_id": "PAC-001",
     *     "patient_document": "12345678",
     *     "summary": {
     *       "total_records": 3,
     *       "total_amount": 150000.00,
     *       "first_service_date": "2026-01-15",
     *       "last_service_date": "2026-06-01"
     *     },
     *     "records": [
     *       {
     *         "id": 1,
     *         "service_date": "2026-06-01",
     *         "service_id": 1,
     *         "service_name": "Urgencias",
     *         "medical_service_id": 3,
     *         "procedure_code": "CUR-001",
     *         "procedure_name": "Curaciones Simples",
     *         "quantity": 2.0,
     *         "unit_price": 50000.00,
     *         "total": 100000.00,
     *         "notes": null,
     *         "is_active": true
     *       }
     *     ]
     *   }
     * }
     */
    public function history(
        string $patientExternalId,
        Request $request,
        GetPatientProcedureHistoryUseCase $useCase,
    ): JsonResponse {
        $filters = $request->only(['service_date_from', 'service_date_to', 'medical_service_id', 'is_active']);

        $history = $useCase->execute($patientExternalId, $filters);

        return $this->success(
            new PatientProcedureHistoryResource($history),
            "Historial del paciente {$patientExternalId}",
        );
    }
}
