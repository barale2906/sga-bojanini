<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $summary = $this->resource;

        return [
            'variant' => $summary->relationLoaded('variant') ? [
                'id'        => $summary->variant->id,
                'lab_brand' => $summary->variant->lab_brand,
                'generic'   => $summary->variant->relationLoaded('genericProduct') ? [
                    'id'      => $summary->variant->genericProduct->id,
                    'barcode' => $summary->variant->genericProduct->barcode,
                    'name'    => $summary->variant->genericProduct->name,
                ] : null,
            ] : [
                'product_variant_id' => $summary->product_variant_id,
            ],
            'warehouse' => $summary->relationLoaded('warehouse') ? [
                'id'   => $summary->warehouse->id,
                'code' => $summary->warehouse->code,
                'name' => $summary->warehouse->name,
            ] : [
                'id' => $summary->warehouse_id,
            ],
            'total_quantity'     => $summary->total_quantity,
            'reserved_quantity'  => $summary->reserved_quantity,
            'available_quantity' => $summary->available_quantity,
            'last_movement_at'   => $summary->last_movement_at?->toIso8601String(),
        ];
    }
}
