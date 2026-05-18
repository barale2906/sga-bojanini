<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Infrastructure\Persistence\Models\CategoryModel;

class CategoryTreeUseCase
{
    public function execute(): array
    {
        $categories = CategoryModel::with('children.children.children')
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return $categories->toArray();
    }
}
