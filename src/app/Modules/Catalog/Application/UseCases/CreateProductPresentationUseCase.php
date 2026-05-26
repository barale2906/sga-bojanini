<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Entities\ProductPresentation;
use App\Modules\Catalog\Domain\Repositories\ProductPresentationRepositoryInterface;
use App\Modules\Catalog\Domain\Services\PresentationHierarchyValidator;

class CreateProductPresentationUseCase
{
    public function __construct(
        private readonly ProductPresentationRepositoryInterface $presentationRepository,
        private readonly PresentationHierarchyValidator $hierarchyValidator,
    ) {}

    public function execute(array $data): ProductPresentation
    {
        $this->hierarchyValidator->validate($data);

        return $this->presentationRepository->save(new ProductPresentation(
            id: null,
            parentId: $data['parent_id'] ?? null,
            name: $data['name'],
            code: $data['code'],
            unitsOfMeasureId: (int) $data['units_of_measure_id'],
            quantityPerParent: $data['quantity_per_parent'] ?? null,
            factorToBase: (int) $data['factor_to_base'],
            level: (int) $data['level'],
            isActive: true,
            sortOrder: (int) ($data['sort_order'] ?? 0),
        ));
    }
}
