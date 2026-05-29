<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Application\DTOs\ProductClassificationData;
use App\Modules\Catalog\Domain\Entities\ProductClassification;
use App\Modules\Catalog\Domain\Repositories\ProductClassificationRepositoryInterface;

class UpdateProductClassificationUseCase
{
    public function __construct(
        private readonly ProductClassificationRepositoryInterface $repository,
    ) {}

    /**
     * @throws \DomainException Si la clasificación no existe o el código está duplicado.
     */
    public function execute(int $id, ProductClassificationData $data): ProductClassification
    {
        if ($this->repository->findById($id) === null) {
            throw new \DomainException('Clasificación de producto no encontrada.');
        }

        $duplicate = $this->repository->findByCode($data->code);
        if ($duplicate !== null && $duplicate->getId() !== $id) {
            throw new \DomainException("Ya existe una clasificación con el código '{$data->code}'.");
        }

        $classification = new ProductClassification(
            id:                      $id,
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
