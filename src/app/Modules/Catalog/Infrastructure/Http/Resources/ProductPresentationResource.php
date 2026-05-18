<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Resources;

use App\Modules\Catalog\Domain\Entities\ProductPresentation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductPresentationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $p = $this->resource;

        if ($p instanceof ProductPresentation) {
            return [
                'id'                  => $p->getId(),
                'product_id'          => $p->getProductId(),
                'parent_id'           => $p->getParentId(),
                'name'                => $p->getName(),
                'code'                => $p->getCode(),
                'units_of_measure_id' => $p->getUnitsOfMeasureId(),
                'quantity_per_parent' => $p->getQuantityPerParent(),
                'factor_to_base'      => $p->getFactorToBase(),
                'level'               => $p->getLevel(),
                'is_purchase_default' => $p->isPurchaseDefault(),
                'is_active'           => $p->isActive(),
                'sort_order'          => $p->getSortOrder(),
            ];
        }

        return parent::toArray($request);
    }
}
