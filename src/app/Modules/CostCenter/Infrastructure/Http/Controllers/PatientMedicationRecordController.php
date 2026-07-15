<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Controllers;

use App\Modules\CostCenter\Application\UseCases\GetProcedureMedicationsUseCase;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class PatientMedicationRecordController extends Controller
{
    use ApiResponse;

    public function index(int $patientProcedureRecord, GetProcedureMedicationsUseCase $useCase): JsonResponse
    {
        $medications = $useCase->execute($patientProcedureRecord);

        return $this->success($medications, 'Medicamentos aplicados en el procedimiento');
    }
}
