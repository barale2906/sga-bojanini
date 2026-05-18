<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $movement = $this->resource;

        if (method_exists($movement, 'getId')) {
            return [
                'id'             => $movement->getId(),
                'warehouse_id'   => $movement->getWarehouseId(),
                'product_id'     => $movement->getProductId(),
                'batch_id'       => $movement->getBatchId(),
                'location_from_id' => $movement->getLocationFromId(),
                'location_to_id'   => $movement->getLocationToId(),
                'movement_type'  => $movement->getMovementType(),
                'quantity'       => $movement->getQuantity(),
                'reason'         => $movement->getReason(),
                'reference_type' => $movement->getReferenceType(),
                'reference_id'   => $movement->getReferenceId(),
                'user_id'        => $movement->getUserId(),
                'created_at'     => $movement->getCreatedAt(),
            ];
        }

        return [
            'id'               => $movement->id,
            'warehouse_id'     => $movement->warehouse_id,
            'product_id'       => $movement->product_id,
            'batch_id'         => $movement->batch_id,
            'location_from_id' => $movement->location_from_id,
            'location_to_id'   => $movement->location_to_id,
            'movement_type'    => $movement->movement_type,
            'quantity'         => $movement->quantity,
            'reason'           => $movement->reason,
            'reference_type'   => $movement->reference_type,
            'reference_id'     => $movement->reference_id,
            'user_id'          => $movement->user_id,
            'created_at'       => $movement->created_at?->toIso8601String(),
            'product_name'     => $movement->relationLoaded('product') ? $movement->product->name : null,
            'batch_lot_number' => $movement->relationLoaded('batch') && $movement->batch
                ? $movement->batch->lot_number
                : null,
            'user_name'        => $movement->relationLoaded('user') && $movement->user
                ? $movement->user->name
                : null,
        ];
    }
}
