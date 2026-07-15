<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases\Concerns;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Services\PresentationConverter;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductVariantModel;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductPresentationModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderItemModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;

trait ManagesPurchaseOrderItems
{
    protected function generatePurchaseOrderCode(): string
    {
        $year = now()->format('Y');
        $prefix = "OC-{$year}-";

        $last = PurchaseOrderModel::where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->value('code');

        $next = 1;

        if ($last !== null) {
            $next = (int) substr($last, -5) + 1;
        }

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{subtotal: float, tax_amount: float, total_amount: float}
     */
    protected function validateAndBuildItems(
        array $items,
        PresentationConverter $converter,
    ): array {
        $subtotal = 0.0;

        foreach ($items as $index => $item) {
            $variant = ProductVariantModel::with('genericProduct')->find($item['product_variant_id']);

            if ($variant === null) {
                throw new \DomainException("Variante en línea {$index} no encontrada.");
            }

            if ($variant->genericProduct->product_type === ProductType::Kit->value) {
                throw new \DomainException("No se pueden incluir productos tipo kit en órdenes de compra (línea {$index}).");
            }

            $presentation = ProductPresentationModel::find($item['product_presentation_id']);

            if ($presentation === null) {
                throw new \DomainException("Presentación no encontrada (línea {$index}).");
            }

            $assignedToProduct = $variant->genericProduct->presentations()
                ->where('product_presentations.id', $presentation->id)
                ->exists();

            if (! $assignedToProduct) {
                throw new \DomainException("La presentación no está asignada al producto (línea {$index}).");
            }

            $quantity = (int) $item['quantity'];

            if ($quantity < 1) {
                throw new \DomainException("La cantidad debe ser mayor a cero (línea {$index}).");
            }

            $item['quantity_requested_base'] = $converter->toBase($presentation->id, $quantity);
            $item['quantity_requested'] = $quantity;
            $item['unit_price'] = (float) $item['unit_price'];
            $item['tax_rate']   = round((float) ($item['tax_rate'] ?? 0), 2);

            $lineSubtotal       = round($quantity * $item['unit_price'], 2);
            $item['tax_amount'] = round($lineSubtotal * $item['tax_rate'] / 100, 2);
            $item['total_price'] = round($lineSubtotal + $item['tax_amount'], 2);
            $items[$index] = $item;

            $subtotal += $lineSubtotal;
        }

        $taxAmount = round(array_sum(array_column($items, 'tax_amount')), 2);

        return [
            'items'        => $items,
            'subtotal'     => round($subtotal, 2),
            'tax_amount'   => $taxAmount,
            'total_amount' => round($subtotal + $taxAmount, 2),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function syncOrderItems(PurchaseOrderModel $order, array $items): void
    {
        $order->items()->delete();

        foreach ($items as $item) {
            PurchaseOrderItemModel::create([
                'purchase_order_id'       => $order->id,
                'product_variant_id'      => $item['product_variant_id'],
                'product_presentation_id' => $item['product_presentation_id'],
                'quantity_requested'      => $item['quantity_requested'],
                'quantity_requested_base' => $item['quantity_requested_base'],
                'unit_price'              => $item['unit_price'],
                'tax_rate'                => $item['tax_rate'],
                'tax_amount'              => $item['tax_amount'],
                'total_price'             => $item['total_price'],
                'notes'                   => $item['notes'] ?? null,
            ]);
        }
    }
}
