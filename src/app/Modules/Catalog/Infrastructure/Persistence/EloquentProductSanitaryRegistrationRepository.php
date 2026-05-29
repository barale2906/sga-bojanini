<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Persistence;

use App\Modules\Catalog\Domain\Entities\ProductSanitaryRegistration;
use App\Modules\Catalog\Domain\Repositories\ProductSanitaryRegistrationRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductSanitaryRegistrationModel;
use DateTimeImmutable;

class EloquentProductSanitaryRegistrationRepository implements ProductSanitaryRegistrationRepositoryInterface
{
    public function findById(int $id): ?ProductSanitaryRegistration
    {
        $model = ProductSanitaryRegistrationModel::find($id);

        return $model ? $this->toDomain($model) : null;
    }

    public function findByProductId(int $productId, bool $onlyActive = false): array
    {
        $query = ProductSanitaryRegistrationModel::where('product_id', $productId);

        if ($onlyActive) {
            $query->where('is_active', true)
                ->where('expiry_date', '>=', now()->toDateString());
        }

        return $query->orderByDesc('expiry_date')
            ->get()
            ->map(fn ($m) => $this->toDomain($m))
            ->toArray();
    }

    public function findByProductAndNumber(int $productId, string $registrationNumber): ?ProductSanitaryRegistration
    {
        $model = ProductSanitaryRegistrationModel::where('product_id', $productId)
            ->where('registration_number', $registrationNumber)
            ->first();

        return $model ? $this->toDomain($model) : null;
    }

    public function save(ProductSanitaryRegistration $registration): ProductSanitaryRegistration
    {
        $model = $registration->getId()
            ? ProductSanitaryRegistrationModel::findOrFail($registration->getId())
            : new ProductSanitaryRegistrationModel();

        $model->product_id          = $registration->getProductId();
        $model->registration_number = $registration->getRegistrationNumber();
        $model->expiry_date         = $registration->getExpiryDate()->format('Y-m-d');
        $model->is_active           = $registration->isActive();
        $model->save();

        return $this->toDomain($model);
    }

    public function delete(int $id): void
    {
        ProductSanitaryRegistrationModel::findOrFail($id)->delete();
    }

    private function toDomain(ProductSanitaryRegistrationModel $model): ProductSanitaryRegistration
    {
        return new ProductSanitaryRegistration(
            id:                 $model->id,
            productId:          $model->product_id,
            registrationNumber: $model->registration_number,
            expiryDate:         new DateTimeImmutable($model->expiry_date->format('Y-m-d')),
            isActive:           (bool) $model->is_active,
        );
    }
}
