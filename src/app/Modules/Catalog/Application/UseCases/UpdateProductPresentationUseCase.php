<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\UseCases;

use App\Modules\Catalog\Domain\Entities\ProductPresentation;
use App\Modules\Catalog\Domain\Repositories\ProductPresentationRepositoryInterface;
use App\Modules\Catalog\Domain\Services\PresentationHierarchyValidator;

class UpdateProductPresentationUseCase
{
    public function __construct(
        private readonly ProductPresentationRepositoryInterface $presentationRepository,
        private readonly PresentationHierarchyValidator $hierarchyValidator,
    ) {}

    public function execute(int $id, array $data): ProductPresentation
    {
        $existing = $this->presentationRepository->findById($id);

        if ($existing === null) {
            throw new \DomainException('Presentación no encontrada.');
        }

        $payload = [
            'parent_id'           => $data['parent_id'] ?? $existing->getParentId(),
            'factor_to_base'      => (int) ($data['factor_to_base'] ?? $existing->getFactorToBase()),
            'quantity_per_parent' => $data['quantity_per_parent'] ?? $existing->getQuantityPerParent(),
            'level'               => (int) ($data['level'] ?? $existing->getLevel()),
        ];

        $this->hierarchyValidator->validate($payload, $id);

        return $this->presentationRepository->save(new ProductPresentation(
            id: $id,
            parentId: $payload['parent_id'],
            name: $data['name'] ?? $existing->getName(),
            code: $data['code'] ?? $existing->getCode(),
            unitsOfMeasureId: (int) ($data['units_of_measure_id'] ?? $existing->getUnitsOfMeasureId()),
            quantityPerParent: $payload['quantity_per_parent'],
            factorToBase: $payload['factor_to_base'],
            level: $payload['level'],
            isActive: (bool) ($data['is_active'] ?? $existing->isActive()),
            sortOrder: (int) ($data['sort_order'] ?? $existing->getSortOrder()),
        ));
    }
}
