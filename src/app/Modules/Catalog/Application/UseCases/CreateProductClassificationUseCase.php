<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Application\DTOs\ProductClassificationData;
use App\Modules\Catalog\Domain\Entities\ProductClassification;
use App\Modules\Catalog\Domain\Repositories\ProductClassificationRepositoryInterface;

class CreateProductClassificationUseCase
{
    public function __construct(
        private readonly ProductClassificationRepositoryInterface $repository,
    ) {}

    /**
     * @throws \DomainException Si ya existe una clasificación con el mismo código.
     */
    public function execute(ProductClassificationData $data): ProductClassification
    {
        if ($this->repository->findByCode($data->code) !== null) {
            throw new \DomainException("Ya existe una clasificación con el código '{$data->code}'.");
        }

        $classification = new ProductClassification(
            id:                      null,
            code:                    $data->code,
            name:                    $data->name,
            description:             $data->description,
            hasSanitaryRegistration: $data->hasSanitaryRegistration,
            hasConcentration:        $data->hasConcentration,
            hasRiskLevel:            $data->hasRiskLevel,
            hasPharmaFields:         $data->hasPharmaFields,
            hasDeviceFields:         $data->hasDeviceFields,
            hasLabBrand:             $data->hasLabBrand,
        );

        return $this->repository->save($classification);
    }
}
