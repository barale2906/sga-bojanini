<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Repositories\ProductClassificationRepositoryInterface;

class ListProductClassificationsUseCase
{
    public function __construct(
        private readonly ProductClassificationRepositoryInterface $repository,
    ) {}

    /**
     * @param array{is_active?: bool|string, search?: string} $filters
     * @return \App\Modules\Catalog\Domain\Entities\ProductClassification[]
     */
    public function execute(array $filters = []): array
    {
        return $this->repository->findAll($filters);
    }
}
