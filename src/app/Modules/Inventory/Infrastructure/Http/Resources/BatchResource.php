<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Resources;

use App\Modules\Warehouse\Domain\Services\WarehouseAccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class BatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $batch = $this->resource;

        if (method_exists($batch, 'getId')) {
            return [
                'id'                  => $batch->getId(),
                'product_variant_id'          => $batch->getProductVariantId(),
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

        $warehouseBreakdown = $batch->relationLoaded('locations')
            ? $this->buildWarehouseBreakdown($batch, $request)
            : null;

        return [
            'id'                  => $batch->id,
            'product_variant_id'          => $batch->product_variant_id,
            'lot_number'          => $batch->lot_number,
            'expiration_date'     => $batch->expiration_date?->format('Y-m-d'),
            'manufacturing_date'  => $batch->manufacturing_date?->format('Y-m-d'),
            'quantity_received'   => $batch->quantity_received,
            'quantity_available'  => $batch->quantity_available,
            'accessible_quantity' => $warehouseBreakdown !== null
                ? (int) collect($warehouseBreakdown)->sum('quantity')
                : null,
            'status'              => $batch->status,
            'days_until_expiry'   => $batch->expiration_date
                ? (int) now()->diffInDays(Carbon::parse($batch->expiration_date), false)
                : null,
            'received_at'         => $batch->received_at?->toIso8601String(),
            'variant'             => $batch->relationLoaded('variant') ? [
                'id'        => $batch->variant->id,
                'lab_brand' => $batch->variant->lab_brand,
                'generic'   => $batch->variant->relationLoaded('genericProduct') ? [
                    'id'      => $batch->variant->genericProduct->id,
                    'barcode' => $batch->variant->genericProduct->barcode,
                    'name'    => $batch->variant->genericProduct->name,
                ] : null,
            ] : null,
            'locations'           => $batch->relationLoaded('locations')
                ? $batch->locations->map(fn ($location) => [
                    'location_id'   => $location->id,
                    'location_name' => $location->name,
                    'location_code' => $location->code,
                    'quantity'      => $location->pivot->quantity,
                    'zone'          => $location->relationLoaded('zone') && $location->zone ? [
                        'zone_id'        => $location->zone->id,
                        'zone_name'      => $location->zone->name,
                        'zone_code'      => $location->zone->code,
                        'warehouse_id'   => $location->zone->warehouse_id,
                        'warehouse_name' => $location->zone->relationLoaded('warehouse') ? $location->zone->warehouse?->name : null,
                        'warehouse_code' => $location->zone->relationLoaded('warehouse') ? $location->zone->warehouse?->code : null,
                    ] : null,
                ])->values()->all()
                : null,
            'warehouses'          => $warehouseBreakdown,
        ];
    }

    /**
     * Construye el desglose de almacenes con cantidad, filtrando solo los que
     * el usuario tiene permitidos. Si el usuario tiene acceso total (super_admin)
     * o no hay usuario autenticado, se devuelven todos los almacenes.
     */
    private function buildWarehouseBreakdown($batch, Request $request): array
    {
        $allowedIds = $this->resolveAllowedWarehouseIds($request);

        return collect($batch->locations)
            ->filter(fn ($loc) => $loc->pivot->quantity > 0
                && $loc->relationLoaded('zone') && $loc->zone
                && $loc->zone->relationLoaded('warehouse') && $loc->zone->warehouse
                && ($allowedIds === null || in_array($loc->zone->warehouse_id, $allowedIds, true)))
            ->groupBy(fn ($loc) => $loc->zone->warehouse->id)
            ->map(fn (Collection $locs) => [
                'id'       => $locs->first()->zone->warehouse->id,
                'code'     => $locs->first()->zone->warehouse->code,
                'name'     => $locs->first()->zone->warehouse->name,
                'quantity' => (int) $locs->sum(fn ($l) => $l->pivot->quantity),
            ])
            ->values()
            ->all();
    }

    /** Devuelve null (sin restricción) para super_admin; array de IDs para los demás. */
    private function resolveAllowedWarehouseIds(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $isAdmin = method_exists($user, 'hasRole') && $user->hasRole('super_administrador');

        return app(WarehouseAccessService::class)->allowedWarehouseIds(
            (int) $user->getAuthIdentifier(),
            $isAdmin,
        );
    }
}
