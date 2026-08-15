<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;

class PreviewConsolidationUseCase
{
    public function execute(array $orderIds): array
    {
        $orders = PurchaseOrderModel::with([
            'items.variant.genericProduct',
            'items.presentation',
            'supplier',
            'warehouse',
        ])
            ->whereIn('id', $orderIds)
            ->get();

        $this->validate($orders, $orderIds);

        $grouped  = [];
        $subtotal = 0.0;
        $totalTax = 0.0;

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $key = "{$item->product_variant_id}-{$item->product_presentation_id}-{$item->unit_price}";

                if (! isset($grouped[$key])) {
                    $grouped[$key] = [
                        'product_variant_id'      => $item->product_variant_id,
                        'product_presentation_id' => $item->product_presentation_id,
                        'unit_price'              => (float) $item->unit_price,
                        'tax_rate'                => (float) $item->tax_rate,
                        'quantity'                => 0.0,
                        'source_order_codes'      => [],
                        'variant'                 => $item->variant,
                        'presentation'            => $item->presentation,
                    ];
                }

                $grouped[$key]['quantity'] += (float) $item->quantity_received;

                if (! in_array($order->code, $grouped[$key]['source_order_codes'], true)) {
                    $grouped[$key]['source_order_codes'][] = $order->code;
                }
            }
        }

        $items        = [];
        $taxByRate    = [];

        foreach ($grouped as $line) {
            $lineSubtotal = round($line['quantity'] * $line['unit_price'], 2);
            $lineTax      = round($lineSubtotal * $line['tax_rate'] / 100, 2);
            $lineTotal    = round($lineSubtotal + $lineTax, 2);

            $variant      = $line['variant'];
            $presentation = $line['presentation'];

            $items[] = [
                'product_variant_id'      => $line['product_variant_id'],
                'product_presentation_id' => $line['product_presentation_id'],
                'quantity'                => $line['quantity'],
                'unit_price'              => $line['unit_price'],
                'tax_rate'                => $line['tax_rate'],
                'subtotal'                => $lineSubtotal,
                'tax_amount'              => $lineTax,
                'total_price'             => $lineTotal,
                'source_order_codes'      => $line['source_order_codes'],
                'variant'                 => $variant ? [
                    'id'        => $variant->id,
                    'lab_brand' => $variant->lab_brand,
                    'brand_sku' => $variant->brand_sku,
                    'generic'   => $variant->genericProduct ? [
                        'id'   => $variant->genericProduct->id,
                        'name' => $variant->genericProduct->name,
                    ] : null,
                ] : null,
                'presentation' => $presentation ? [
                    'id'   => $presentation->id,
                    'code' => $presentation->code,
                    'name' => $presentation->name,
                ] : null,
            ];

            $subtotal += $lineSubtotal;
            $totalTax += $lineTax;

            $rateKey = (string) $line['tax_rate'];
            if (! isset($taxByRate[$rateKey])) {
                $taxByRate[$rateKey] = ['taxable_base' => 0.0, 'tax_amount' => 0.0];
            }
            $taxByRate[$rateKey]['taxable_base'] += $lineSubtotal;
            $taxByRate[$rateKey]['tax_amount']   += $lineTax;
        }

        usort($items, fn ($a, $b) => strcmp(
            $a['variant']['generic']['name'] ?? '',
            $b['variant']['generic']['name'] ?? '',
        ));

        ksort($taxByRate, SORT_NUMERIC);

        $taxBreakdown = array_map(
            fn (string $rate, array $bucket) => [
                'rate'         => (float) $rate,
                'taxable_base' => round($bucket['taxable_base'], 2),
                'tax_amount'   => round($bucket['tax_amount'], 2),
            ],
            array_keys($taxByRate),
            array_values($taxByRate),
        );

        $supplier = $orders->first()->supplier;

        return [
            'supplier' => [
                'id'     => $supplier->id,
                'name'   => $supplier->name,
                'tax_id' => $supplier->tax_id,
            ],
            'purchase_orders' => $orders->map(fn ($o) => [
                'id'           => $o->id,
                'code'         => $o->code,
                'total_amount' => (float) $o->total_amount,
                'warehouse'    => $o->warehouse ? [
                    'id'   => $o->warehouse->id,
                    'name' => $o->warehouse->name,
                ] : null,
            ])->values()->all(),
            'items'         => $items,
            'subtotal'      => round($subtotal, 2),
            'tax_breakdown' => $taxBreakdown,
            'tax_amount'    => round($totalTax, 2),
            'total_amount'  => round($subtotal + $totalTax, 2),
        ];
    }

    private function validate(\Illuminate\Database\Eloquent\Collection $orders, array $requestedIds): void
    {
        if ($orders->count() !== count(array_unique($requestedIds))) {
            throw new \DomainException('Una o más órdenes de compra no fueron encontradas.');
        }

        $nonReceived = $orders->filter(fn ($o) => $o->status !== PurchaseOrderStatus::Received->value);
        if ($nonReceived->isNotEmpty()) {
            $codes = $nonReceived->pluck('code')->implode(', ');
            throw new \DomainException("Solo se pueden consolidar órdenes en estado 'received'. Inválidas: {$codes}.");
        }

        $alreadyConsolidated = $orders->filter(fn ($o) => $o->consolidated_order_id !== null);
        if ($alreadyConsolidated->isNotEmpty()) {
            $codes = $alreadyConsolidated->pluck('code')->implode(', ');
            throw new \DomainException("Las siguientes órdenes ya fueron consolidadas: {$codes}.");
        }

        $supplierIds = $orders->pluck('supplier_id')->unique();
        if ($supplierIds->count() > 1) {
            throw new \DomainException('Todas las órdenes deben pertenecer al mismo proveedor.');
        }
    }
}
