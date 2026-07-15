<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Persistence;

use App\Modules\CostCenter\Domain\Entities\ClinicalTemplate;
use App\Modules\CostCenter\Domain\Repositories\ClinicalTemplateRepositoryInterface;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\ClinicalTemplateModel;

class EloquentClinicalTemplateRepository implements ClinicalTemplateRepositoryInterface
{
    public function findById(int $id): ?ClinicalTemplate
    {
        $model = ClinicalTemplateModel::with('medicalService')->find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByServiceId(int $medicalServiceId): ?ClinicalTemplate
    {
        $model = ClinicalTemplateModel::with('medicalService')
            ->where('medical_service_id', $medicalServiceId)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function findAll(array $filters = []): array
    {
        $query = ClinicalTemplateModel::with('medicalService');

        if (isset($filters['medical_service_id'])) {
            $query->where('medical_service_id', $filters['medical_service_id']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->orderBy('title')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->toArray();
    }

    public function save(ClinicalTemplate $template): ClinicalTemplate
    {
        $model = $template->getId()
            ? ClinicalTemplateModel::findOrFail($template->getId())
            : new ClinicalTemplateModel();

        $model->medical_service_id = $template->getMedicalServiceId();
        $model->title              = $template->getTitle();
        $model->content            = $template->getContent();
        $model->is_active          = $template->isActive();
        $model->save();

        $model->load('medicalService');

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        ClinicalTemplateModel::findOrFail($id)->delete();
    }

    private function toDomain(ClinicalTemplateModel $model): ClinicalTemplate
    {
        return new ClinicalTemplate(
            id:                 $model->id,
            medicalServiceId:   $model->medical_service_id,
            title:              $model->title,
            content:            $model->content,
            isActive:           (bool) $model->is_active,
            medicalServiceName: $model->relationLoaded('medicalService') ? $model->medicalService?->name : null,
        );
    }
}
