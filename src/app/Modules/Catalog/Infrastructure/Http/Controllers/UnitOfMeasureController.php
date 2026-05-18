<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Application\DTOs\UnitOfMeasureData;
use App\Modules\Catalog\Application\UseCases\CreateUnitOfMeasureUseCase;
use App\Modules\Catalog\Application\UseCases\DeleteUnitOfMeasureUseCase;
use App\Modules\Catalog\Application\UseCases\UpdateUnitOfMeasureUseCase;
use App\Modules\Catalog\Domain\Repositories\UnitOfMeasureRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Http\Requests\StoreUnitOfMeasureRequest;
use App\Modules\Catalog\Infrastructure\Http\Requests\UpdateUnitOfMeasureRequest;
use App\Modules\Catalog\Infrastructure\Http\Resources\UnitOfMeasureResource;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UnitOfMeasureController extends Controller
{
    use ApiResponse;

    public function index(Request $request, UnitOfMeasureRepositoryInterface $repository): JsonResponse
    {
        $units = $repository->findAll([
            'is_active' => $request->query('is_active'),
            'is_base'   => $request->query('is_base'),
            'search'    => $request->query('search'),
        ]);

        return $this->success(
            UnitOfMeasureResource::collection($units),
            'Listado de unidades de medida'
        );
    }

    public function store(StoreUnitOfMeasureRequest $request, CreateUnitOfMeasureUseCase $useCase): JsonResponse
    {
        $data = new UnitOfMeasureData(
            name: $request->validated('name'),
            abbreviation: $request->validated('abbreviation'),
            isBase: (bool) $request->validated('is_base', false),
        );

        $unit = $useCase->execute($data);

        return $this->created(
            new UnitOfMeasureResource($unit),
            'Unidad de medida creada exitosamente'
        );
    }

    public function show(int $unit_of_measure, UnitOfMeasureRepositoryInterface $repository): JsonResponse
    {
        $entity = $repository->findById($unit_of_measure);

        if ($entity === null) {
            return $this->error('Unidad de medida no encontrada', 404);
        }

        return $this->success(
            new UnitOfMeasureResource($entity),
            'Detalle de la unidad de medida'
        );
    }

    public function update(int $unit_of_measure, UpdateUnitOfMeasureRequest $request, UpdateUnitOfMeasureUseCase $useCase): JsonResponse
    {
        $data = new UnitOfMeasureData(
            name: $request->validated('name'),
            abbreviation: $request->validated('abbreviation'),
            isBase: (bool) $request->validated('is_base', false),
        );

        $entity = $useCase->execute($unit_of_measure, $data);

        return $this->success(
            new UnitOfMeasureResource($entity),
            'Unidad de medida actualizada exitosamente'
        );
    }

    public function destroy(int $unit_of_measure, DeleteUnitOfMeasureUseCase $useCase): JsonResponse
    {
        $useCase->execute($unit_of_measure);

        return $this->noContent('Unidad de medida eliminada');
    }
}
