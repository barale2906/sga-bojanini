<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Controllers;

use App\Modules\CostCenter\Application\DTOs\PatientClinicalEvolutionData;
use App\Modules\CostCenter\Application\UseCases\CreatePatientClinicalEvolutionUseCase;
use App\Modules\CostCenter\Application\UseCases\DeletePatientClinicalEvolutionUseCase;
use App\Modules\CostCenter\Application\UseCases\ListPatientClinicalEvolutionsUseCase;
use App\Modules\CostCenter\Application\UseCases\UpdatePatientClinicalEvolutionUseCase;
use App\Modules\CostCenter\Infrastructure\Http\Requests\StorePatientClinicalEvolutionRequest;
use App\Modules\CostCenter\Infrastructure\Http\Requests\UpdatePatientClinicalEvolutionRequest;
use App\Modules\CostCenter\Infrastructure\Http\Resources\PatientClinicalEvolutionResource;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\PatientProcedureRecordModel;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class PatientClinicalEvolutionController extends Controller
{
    use ApiResponse;

    public function index(int $patientProcedureRecord, ListPatientClinicalEvolutionsUseCase $useCase): JsonResponse
    {
        $record = PatientProcedureRecordModel::find($patientProcedureRecord);

        if ($record === null) {
            return $this->error('Registro de procedimiento no encontrado', 404);
        }

        $evolutions = $useCase->execute($patientProcedureRecord);

        return $this->success(
            PatientClinicalEvolutionResource::collection($evolutions),
            'Evoluciones clínicas del registro',
        );
    }

    public function store(
        StorePatientClinicalEvolutionRequest $request,
        int $patientProcedureRecord,
        CreatePatientClinicalEvolutionUseCase $useCase,
    ): JsonResponse {
        $validated = $request->validated();

        $recordedAt = isset($validated['recorded_at'])
            ? new DateTimeImmutable($validated['recorded_at'])
            : new DateTimeImmutable();

        $evolution = $useCase->execute(new PatientClinicalEvolutionData(
            patientProcedureRecordId: $patientProcedureRecord,
            content:                  $validated['content'],
            userId:                   $request->user()->id,
            recordedAt:               $recordedAt,
        ));

        return $this->created(new PatientClinicalEvolutionResource($evolution), 'Evolución clínica registrada');
    }

    public function update(
        UpdatePatientClinicalEvolutionRequest $request,
        int $patientProcedureRecord,
        int $evolution,
        UpdatePatientClinicalEvolutionUseCase $useCase,
    ): JsonResponse {
        $validated = $request->validated();

        $updated = $useCase->execute($evolution, $validated['content']);

        return $this->success(new PatientClinicalEvolutionResource($updated), 'Evolución clínica actualizada');
    }

    public function destroy(
        int $patientProcedureRecord,
        int $evolution,
        DeletePatientClinicalEvolutionUseCase $useCase,
    ): JsonResponse {
        $useCase->execute($evolution);

        return $this->noContent('Evolución clínica eliminada');
    }
}
