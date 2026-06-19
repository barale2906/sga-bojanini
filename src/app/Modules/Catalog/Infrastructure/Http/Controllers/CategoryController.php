<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Controllers;

use App\Modules\Catalog\Application\DTOs\CategoryData;
use App\Modules\Catalog\Application\UseCases\CategoryTreeUseCase;
use App\Modules\Catalog\Application\UseCases\CreateCategoryUseCase;
use App\Modules\Catalog\Application\UseCases\DeleteCategoryUseCase;
use App\Modules\Catalog\Application\UseCases\UpdateCategoryUseCase;
use App\Modules\Catalog\Domain\Repositories\CategoryRepositoryInterface;
use App\Modules\Catalog\Infrastructure\Http\Requests\StoreCategoryRequest;
use App\Modules\Catalog\Infrastructure\Http\Requests\UpdateCategoryRequest;
use App\Modules\Catalog\Infrastructure\Http\Resources\CategoryResource;
use App\Modules\Shared\Infrastructure\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CategoryController extends Controller
{
    use ApiResponse;

    public function index(Request $request, CategoryRepositoryInterface $repository): JsonResponse
    {
        $categories = $repository->findAll([
            'parent_id' => $request->query('parent_id'),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
            'search'    => $request->query('search'),
        ]);

        return $this->success(
            CategoryResource::collection($categories),
            'Listado de categorías'
        );
    }

    public function store(StoreCategoryRequest $request, CreateCategoryUseCase $useCase): JsonResponse
    {
        $data = new CategoryData(
            parentId: $request->validated('parent_id'),
            name: $request->validated('name'),
            code: $request->validated('code'),
            description: $request->validated('description'),
        );

        $category = $useCase->execute($data);

        return $this->created(
            new CategoryResource($category),
            'Categoría creada exitosamente'
        );
    }

    public function show(int $category, CategoryRepositoryInterface $repository): JsonResponse
    {
        $entity = $repository->findById($category);

        if ($entity === null) {
            return $this->error('Categoría no encontrada', 404);
        }

        return $this->success(
            new CategoryResource($entity),
            'Detalle de la categoría'
        );
    }

    public function update(int $category, UpdateCategoryRequest $request, UpdateCategoryUseCase $useCase): JsonResponse
    {
        $data = new CategoryData(
            parentId: $request->validated('parent_id'),
            name: $request->validated('name'),
            code: $request->validated('code'),
            description: $request->validated('description'),
        );

        $entity = $useCase->execute($category, $data);

        return $this->success(
            new CategoryResource($entity),
            'Categoría actualizada exitosamente'
        );
    }

    public function destroy(int $category, DeleteCategoryUseCase $useCase): JsonResponse
    {
        $useCase->execute($category);

        return $this->noContent('Categoría eliminada');
    }

    public function tree(CategoryTreeUseCase $useCase): JsonResponse
    {
        $tree = $useCase->execute();

        return $this->success($tree, 'Árbol de categorías');
    }
}
