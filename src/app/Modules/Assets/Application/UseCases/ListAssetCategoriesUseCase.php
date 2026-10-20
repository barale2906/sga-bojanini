<?php

declare(strict_types=1);

namespace App\Modules\Assets\Application\UseCases;

use App\Modules\Assets\Domain\Entities\AssetCategory;
use App\Modules\Assets\Domain\Repositories\AssetCategoryRepositoryInterface;

class ListAssetCategoriesUseCase
{
    public function __construct(
        private readonly AssetCategoryRepositoryInterface $repository,
    ) {}

    /** @return AssetCategory[] */
    public function execute(array $filters = []): array
    {
        return $this->repository->findAll($filters);
    }
}
