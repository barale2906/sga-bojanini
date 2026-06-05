<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Resources;

use App\Modules\CostCenter\Domain\Entities\ProcedurePrice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcedurePriceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $price = $this->resource;

        if ($price instanceof ProcedurePrice) {
            return [
                'id'                 => $price->getId(),
                'medical_service_id' => $price->getMedicalServiceId(),
                'unit_price'         => $price->getUnitPrice(),
                'effective_from'     => $price->getEffectiveFrom()->format('Y-m-d'),
                'effective_to'       => $price->getEffectiveTo()?->format('Y-m-d'),
                'is_active'          => $price->isActive(),
                'is_currently_valid' => $price->isCurrentlyActive(),
                'notes'              => $price->getNotes(),
            ];
        }

        return [
            'id'                 => $price->id,
            'medical_service_id' => $price->medical_service_id,
            'unit_price'         => $price->unit_price,
            'effective_from'     => $price->effective_from?->format('Y-m-d'),
            'effective_to'       => $price->effective_to?->format('Y-m-d'),
            'is_active'          => $price->is_active,
            'is_currently_valid' => null,
            'notes'              => $price->notes,
        ];
    }
}
