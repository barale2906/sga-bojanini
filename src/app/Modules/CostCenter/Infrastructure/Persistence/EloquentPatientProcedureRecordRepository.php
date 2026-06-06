<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Persistence;

use App\Modules\CostCenter\Domain\Entities\PatientProcedureRecord;
use App\Modules\CostCenter\Domain\Repositories\PatientProcedureRecordRepositoryInterface;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\PatientProcedureRecordModel;
use DateTimeImmutable;

class EloquentPatientProcedureRecordRepository implements PatientProcedureRecordRepositoryInterface
{
    public function findById(int $id): ?PatientProcedureRecord
    {
        $model = PatientProcedureRecordModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(array $filters = []): array
    {
        $query = PatientProcedureRecordModel::query();

        if (isset($filters['medical_service_id'])) {
            $query->where('medical_service_id', $filters['medical_service_id']);
        }

        if (isset($filters['patient_external_id'])) {
            $query->where('patient_external_id', $filters['patient_external_id']);
        }

        if (isset($filters['patient_document'])) {
            $query->where('patient_document', 'like', "%{$filters['patient_document']}%");
        }

        if (isset($filters['service_date_from'])) {
            $query->where('service_date', '>=', $filters['service_date_from']);
        }

        if (isset($filters['service_date_to'])) {
            $query->where('service_date', '<=', $filters['service_date_to']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->with('medicalService')
            ->orderByDesc('service_date')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->toArray();
    }

    public function findByPatientWithService(string $patientExternalId, array $filters = []): array
    {
        $query = PatientProcedureRecordModel::with(['medicalService', 'medicalService.parent'])
            ->where('patient_external_id', $patientExternalId);

        if (isset($filters['service_date_from'])) {
            $query->where('service_date', '>=', $filters['service_date_from']);
        }

        if (isset($filters['service_date_to'])) {
            $query->where('service_date', '<=', $filters['service_date_to']);
        }

        if (isset($filters['medical_service_id'])) {
            $query->where('medical_service_id', $filters['medical_service_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderByDesc('service_date')
            ->get()
            ->map(fn ($m) => [
                'id'                  => $m->id,
                'medical_service_id'  => $m->medical_service_id,
                'procedure_code'      => $m->medicalService?->code,
                'procedure_name'      => $m->medicalService?->name,
                'service_id'          => $m->medicalService?->parent_id,
                'service_name'        => $m->medicalService?->parent?->name,
                'patient_external_id' => $m->patient_external_id,
                'patient_document'    => $m->patient_document,
                'patient_first_name'  => $m->patient_first_name,
                'patient_last_name'   => $m->patient_last_name,
                'quantity'            => (float) $m->quantity,
                'unit_price'          => (float) $m->unit_price,
                'total'               => (float) $m->total,
                'service_date'        => $m->service_date->format('Y-m-d'),
                'notes'               => $m->notes,
                'is_active'           => (bool) $m->is_active,
            ])
            ->toArray();
    }

    public function save(PatientProcedureRecord $record): PatientProcedureRecord
    {
        $model = $record->getId()
            ? PatientProcedureRecordModel::findOrFail($record->getId())
            : new PatientProcedureRecordModel();

        $model->medical_service_id  = $record->getMedicalServiceId();
        $model->patient_external_id = $record->getPatientExternalId();
        $model->patient_document    = $record->getPatientDocument();
        $model->patient_first_name  = $record->getPatientFirstName();
        $model->patient_last_name   = $record->getPatientLastName();
        $model->quantity            = $record->getQuantity();
        $model->unit_price          = $record->getUnitPrice();
        $model->total               = $record->getTotal();
        $model->service_date        = $record->getServiceDate()->format('Y-m-d');
        $model->notes               = $record->getNotes();
        $model->is_active           = $record->isActive();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        PatientProcedureRecordModel::findOrFail($id)->delete();
    }

    private function toDomain(PatientProcedureRecordModel $model): PatientProcedureRecord
    {
        return new PatientProcedureRecord(
            id:                 $model->id,
            medicalServiceId:   $model->medical_service_id,
            patientExternalId:  $model->patient_external_id,
            patientDocument:    $model->patient_document,
            patientFirstName:   $model->patient_first_name,
            patientLastName:    $model->patient_last_name,
            quantity:           (float) $model->quantity,
            unitPrice:          (float) $model->unit_price,
            total:              (float) $model->total,
            serviceDate:        new DateTimeImmutable($model->service_date->format('Y-m-d')),
            notes:              $model->notes,
            isActive:           (bool) $model->is_active,
            medicalServiceName: $model->relationLoaded('medicalService') ? $model->medicalService?->name : null,
        );
    }
}
