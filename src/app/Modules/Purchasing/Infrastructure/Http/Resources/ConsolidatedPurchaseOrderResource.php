<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Resources;

use App\Modules\Purchasing\Infrastructure\Persistence\Models\ConsolidatedPurchaseOrderModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ConsolidatedPurchaseOrderModel */
class ConsolidatedPurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'code'         => $this->code,
            'supplier_id'  => $this->supplier_id,
            'period_from'  => $this->period_from?->format('Y-m-d'),
            'period_to'    => $this->period_to?->format('Y-m-d'),
            'subtotal'      => $this->subtotal,
            'tax_breakdown' => $this->whenLoaded('items', fn () => $this->buildTaxBreakdown()),
            'tax_amount'    => $this->tax_amount,
            'total_amount'  => $this->total_amount,
            'notes'        => $this->notes,
            'created_by'   => $this->created_by,
            'created_at'   => $this->created_at?->toIso8601String(),
            'updated_at'   => $this->updated_at?->toIso8601String(),
            'supplier'     => $this->whenLoaded('supplier', fn () => [
                'id'   => $this->supplier->id,
                'name' => $this->supplier->name,
            ]),
            'items'           => ConsolidatedPurchaseOrderItemResource::collection(
                $this->whenLoaded('items', fn () => $this->items->sortBy(
                    fn ($item) => $item->relationLoaded('variant') && $item->variant?->relationLoaded('genericProduct')
                        ? $item->variant->genericProduct?->name ?? ''
                        : '',
                )->values())
            ),
            'purchase_orders' => PurchaseOrderResource::collection($this->whenLoaded('purchaseOrders')),
        ];
    }

    private function buildTaxBreakdown(): array
    {
        $byRate = [];

        foreach ($this->items as $item) {
            $rate = (string) $item->tax_rate;
            if (! isset($byRate[$rate])) {
                $byRate[$rate] = ['taxable_base' => 0.0, 'tax_amount' => 0.0];
            }
            $lineSubtotal = round((float) $item->quantity * (float) $item->unit_price, 2);
            $byRate[$rate]['taxable_base'] += $lineSubtotal;
            $byRate[$rate]['tax_amount']   += (float) $item->tax_amount;
        }

        ksort($byRate, SORT_NUMERIC);

        return array_map(
            fn (string $rate, array $bucket) => [
                'rate'         => (float) $rate,
                'taxable_base' => round($bucket['taxable_base'], 2),
                'tax_amount'   => round($bucket['tax_amount'], 2),
            ],
            array_keys($byRate),
            array_values($byRate),
        );
    }
}
