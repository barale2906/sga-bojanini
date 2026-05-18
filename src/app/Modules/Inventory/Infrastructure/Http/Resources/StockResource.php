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
            'product' => $summary->relationLoaded('product') ? [
                'id'   => $summary->product->id,
                'code' => $summary->product->code,
                'name' => $summary->product->name,
            ] : [
                'id' => $summary->product_id,
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
