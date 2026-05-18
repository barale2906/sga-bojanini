<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Application\DTOs\UnitOfMeasureData;
use App\Modules\Catalog\Domain\Entities\UnitOfMeasure;
use App\Modules\Catalog\Domain\Repositories\UnitOfMeasureRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Persistence\Models\UnitOfMeasureModel;

class CreateUnitOfMeasureUseCase
{
    public function __construct(
        private readonly UnitOfMeasureRepositoryInterface $repository,
    ) {}

    public function execute(UnitOfMeasureData $data): UnitOfMeasure
    {
        if (UnitOfMeasureModel::where('abbreviation', $data->abbreviation)->exists()) {
            throw new \DomainException("Ya existe una unidad de medida con la abreviatura '{$data->abbreviation}'.");
        }

        $unit = new UnitOfMeasure(
            id: null,
            name: $data->name,
            abbreviation: $data->abbreviation,
            isBase: $data->isBase,
        );

        return $this->repository->save($unit);
    }
}
