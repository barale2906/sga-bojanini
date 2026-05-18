<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases\Concerns;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Services\PresentationConverter;
use App\Modules\Catalog\Infrastructure\Persistence\Models\ProductModel;
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
            $product = ProductModel::find($item['product_id']);

            if ($product === null) {
                throw new \DomainException("Producto en línea {$index} no encontrado.");
            }

            if ($product->product_type === ProductType::Kit->value) {
                throw new \DomainException("No se pueden incluir productos tipo kit en órdenes de compra (línea {$index}).");
            }

            $presentation = ProductPresentationModel::where('id', $item['product_presentation_id'])
                ->where('product_id', $product->id)
                ->first();

            if ($presentation === null) {
                throw new \DomainException("La presentación no pertenece al producto (línea {$index}).");
            }

            $quantity = (int) $item['quantity'];

            if ($quantity < 1) {
                throw new \DomainException("La cantidad debe ser mayor a cero (línea {$index}).");
            }

            $item['quantity_requested_base'] = $converter->toBase($presentation->id, $quantity);
            $item['quantity_requested'] = $quantity;
            $item['unit_price'] = (float) $item['unit_price'];
            $item['total_price'] = round($quantity * $item['unit_price'], 2);
            $items[$index] = $item;

            $subtotal += $item['total_price'];
        }

        $taxRate = (float) config('sga.purchasing.tax_rate', 0);
        $taxAmount = round($subtotal * $taxRate, 2);

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
                'product_id'              => $item['product_id'],
                'product_presentation_id' => $item['product_presentation_id'],
                'quantity_requested'      => $item['quantity_requested'],
                'quantity_requested_base' => $item['quantity_requested_base'],
                'unit_price'              => $item['unit_price'],
                'total_price'             => $item['total_price'],
                'notes'                   => $item['notes'] ?? null,
            ]);
        }
    }
}
