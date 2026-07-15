<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Persistence;

use App\Modules\CostCenter\Domain\Entities\PatientClinicalEvolution;
use App\Modules\CostCenter\Domain\Repositories\PatientClinicalEvolutionRepositoryInterface;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\PatientClinicalEvolutionModel;
use DateTimeImmutable;

class EloquentPatientClinicalEvolutionRepository implements PatientClinicalEvolutionRepositoryInterface
{
    public function findById(int $id): ?PatientClinicalEvolution
    {
        $model = PatientClinicalEvolutionModel::with('user')->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByRecordId(int $patientProcedureRecordId): array
    {
        return PatientClinicalEvolutionModel::with('user')
            ->where('patient_procedure_record_id', $patientProcedureRecordId)
            ->orderByDesc('recorded_at')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->toArray();
    }

    public function save(PatientClinicalEvolution $evolution): PatientClinicalEvolution
    {
        $model = $evolution->getId()
            ? PatientClinicalEvolutionModel::findOrFail($evolution->getId())
            : new PatientClinicalEvolutionModel();

        $model->patient_procedure_record_id = $evolution->getPatientProcedureRecordId();
        $model->content                     = $evolution->getContent();
        $model->user_id                     = $evolution->getUserId();
        $model->recorded_at                 = $evolution->getRecordedAt()->format('Y-m-d H:i:s');
        $model->save();

        $model->load('user');

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        PatientClinicalEvolutionModel::findOrFail($id)->delete();
    }

    private function toDomain(PatientClinicalEvolutionModel $model): PatientClinicalEvolution
    {
        return new PatientClinicalEvolution(
            id:                       $model->id,
            patientProcedureRecordId: $model->patient_procedure_record_id,
            content:                  $model->content,
            userId:                   $model->user_id,
            recordedAt:               new DateTimeImmutable($model->recorded_at->format('Y-m-d H:i:s')),
            userName:                 $model->relationLoaded('user') ? $model->user?->name : null,
        );
    }
}
