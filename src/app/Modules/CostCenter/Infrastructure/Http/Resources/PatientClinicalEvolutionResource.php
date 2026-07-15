<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Resources;

use App\Modules\CostCenter\Domain\Entities\PatientClinicalEvolution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientClinicalEvolutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $e = $this->resource;

        if ($e instanceof PatientClinicalEvolution) {
            return [
                'id'                          => $e->getId(),
                'patient_procedure_record_id' => $e->getPatientProcedureRecordId(),
                'content'                     => $e->getContent(),
                'user_id'                     => $e->getUserId(),
                'user_name'                   => $e->getUserName(),
                'recorded_at'                 => $e->getRecordedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return [
            'id'                          => $e->id,
            'patient_procedure_record_id' => $e->patient_procedure_record_id,
            'content'                     => $e->content,
            'user_id'                     => $e->user_id,
            'user_name'                   => $e->user?->name,
            'recorded_at'                 => $e->recorded_at?->format('Y-m-d H:i:s'),
        ];
    }
}
