<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $batch = $this->resource;

        if (method_exists($batch, 'getId')) {
            return [
                'id'                  => $batch->getId(),
                'product_id'          => $batch->getProductId(),
                'lot_number'          => $batch->getLotNumber(),
                'expiration_date'     => $batch->getExpirationDate(),
                'manufacturing_date'  => $batch->getManufacturingDate(),
                'quantity_received'   => $batch->getQuantityReceived(),
                'quantity_available'  => $batch->getQuantityAvailable(),
                'status'              => $batch->getStatus(),
                'days_until_expiry'   => $batch->daysUntilExpiry(),
                'received_at'         => $batch->getReceivedAt(),
            ];
        }

        return [
            'id'                  => $batch->id,
            'product_id'          => $batch->product_id,
            'lot_number'          => $batch->lot_number,
            'expiration_date'     => $batch->expiration_date?->format('Y-m-d'),
            'manufacturing_date'  => $batch->manufacturing_date?->format('Y-m-d'),
            'quantity_received'   => $batch->quantity_received,
            'quantity_available'  => $batch->quantity_available,
            'status'              => $batch->status,
            'days_until_expiry'   => $batch->expiration_date
                ? (int) now()->diffInDays(Carbon::parse($batch->expiration_date), false)
                : null,
            'received_at'         => $batch->received_at?->toIso8601String(),
            'product'             => $batch->relationLoaded('product') ? [
                'id'   => $batch->product->id,
                'code' => $batch->product->code,
                'name' => $batch->product->name,
            ] : null,
            'locations'           => $batch->relationLoaded('locations')
                ? $batch->locations->map(fn ($location) => [
                    'location_id'   => $location->id,
                    'location_name' => $location->name,
                    'location_code' => $location->code,
                    'quantity'      => $location->pivot->quantity,
                    'zone'          => $location->relationLoaded('zone') && $location->zone ? [
                        'zone_id'      => $location->zone->id,
                        'zone_name'    => $location->zone->name,
                        'zone_code'    => $location->zone->code,
                        'warehouse_id' => $location->zone->warehouse_id,
                    ] : null,
                ])->values()->all()
                : null,
        ];
    }
}
