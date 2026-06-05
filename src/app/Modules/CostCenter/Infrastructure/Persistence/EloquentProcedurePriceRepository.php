<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Persistence;

use App\Modules\CostCenter\Domain\Entities\ProcedurePrice;
use App\Modules\CostCenter\Domain\Repositories\ProcedurePriceRepositoryInterface;
use App\Modules\CostCenter\Infrastructure\Persistence\Models\ProcedurePriceModel;
use DateTimeImmutable;

class EloquentProcedurePriceRepository implements ProcedurePriceRepositoryInterface
{
    public function findById(int $id): ?ProcedurePrice
    {
        $model = ProcedurePriceModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByProcedureId(int $medicalServiceId, array $filters = []): array
    {
        $query = ProcedurePriceModel::where('medical_service_id', $medicalServiceId);

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['effective_from'])) {
            $query->where('effective_from', '>=', $filters['effective_from']);
        }

        return $query->orderByDesc('effective_from')->get()->map(fn ($m) => $this->toDomain($m))->toArray();
    }

    public function findCurrentPrice(int $medicalServiceId, ?DateTimeImmutable $date = null): ?ProcedurePrice
    {
        $today = ($date ?? new DateTimeImmutable('today'))->format('Y-m-d');

        $model = ProcedurePriceModel::where('medical_service_id', $medicalServiceId)
            ->where('is_active', true)
            ->where('effective_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today);
            })
            ->orderByDesc('effective_from')
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(ProcedurePrice $price): ProcedurePrice
    {
        $model = $price->getId()
            ? ProcedurePriceModel::findOrFail($price->getId())
            : new ProcedurePriceModel();

        $model->medical_service_id = $price->getMedicalServiceId();
        $model->unit_price         = $price->getUnitPrice();
        $model->effective_from     = $price->getEffectiveFrom()->format('Y-m-d');
        $model->effective_to       = $price->getEffectiveTo()?->format('Y-m-d');
        $model->is_active          = $price->isActive();
        $model->notes              = $price->getNotes();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        ProcedurePriceModel::findOrFail($id)->delete();
    }

    private function toDomain(ProcedurePriceModel $model): ProcedurePrice
    {
        return new ProcedurePrice(
            id:               $model->id,
            medicalServiceId: $model->medical_service_id,
            unitPrice:        (float) $model->unit_price,
            effectiveFrom:    new DateTimeImmutable($model->effective_from->format('Y-m-d')),
            effectiveTo:      $model->effective_to
                                ? new DateTimeImmutable($model->effective_to->format('Y-m-d'))
                                : null,
            isActive:         (bool) $model->is_active,
            notes:            $model->notes,
        );
    }
}
