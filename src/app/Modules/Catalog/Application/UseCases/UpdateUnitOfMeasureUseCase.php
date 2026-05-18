<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Application\DTOs\UnitOfMeasureData;
use App\Modules\Catalog\Domain\Entities\UnitOfMeasure;
use App\Modules\Catalog\Domain\Repositories\UnitOfMeasureRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;

class UpdateUnitOfMeasureUseCase
{
    public function __construct(
        private readonly UnitOfMeasureRepositoryInterface $repository,
    ) {}

    public function execute(int $id, UnitOfMeasureData $data): UnitOfMeasure
    {
        $existing = $this->repository->findById($id);

        if ($existing === null) {
            throw new \DomainException('Unidad de medida no encontrada.');
        }

        $duplicate = UnitOfMeasureModel::where('abbreviation', $data->abbreviation)
            ->where('id', '!=', $id)
            ->exists();

        if ($duplicate) {
            throw new \DomainException("Ya existe una unidad de medida con la abreviatura '{$data->abbreviation}'.");
        }

        $unit = new UnitOfMeasure(
            id: $id,
            name: $data->name,
            abbreviation: $data->abbreviation,
            isActive: $existing->isActive(),
            isBase: $data->isBase,
        );

        return $this->repository->save($unit);
    }
}
