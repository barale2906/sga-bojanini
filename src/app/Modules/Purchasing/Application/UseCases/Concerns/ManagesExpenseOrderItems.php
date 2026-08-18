<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases\Concerns;

use App\Modules\Purchasing\Infrastructure\Persistence\Models\ExpenseOrderItemModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;

trait ManagesExpenseOrderItems
{
    protected function generateExpenseOrderCode(): string
    {
        $year   = now()->format('Y');
        $prefix = "OG-{$year}-";

        $last = PurchaseOrderModel::where('order_type', 'expense')
            ->where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->value('code');

        $next = $last !== null ? (int) substr($last, -5) + 1 : 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{items: array, subtotal: float, tax_amount: float, total_amount: float}
     */
    protected function buildExpenseItems(array $items): array
    {
        $subtotal = 0.0;

        foreach ($items as $index => $item) {
            $quantity = (float) $item['quantity'];

            if ($quantity <= 0) {
                throw new \DomainException("La cantidad debe ser mayor a cero (línea {$index}).");
            }

            $unitPrice  = (float) $item['unit_price'];
            $taxRate    = round((float) ($item['tax_rate'] ?? 0), 2);
            $lineSubtot = round($quantity * $unitPrice, 2);
            $taxAmount  = round($lineSubtot * $taxRate / 100, 2);

            $items[$index]['quantity']    = $quantity;
            $items[$index]['unit_price']  = $unitPrice;
            $items[$index]['tax_rate']    = $taxRate;
            $items[$index]['tax_amount']  = $taxAmount;
            $items[$index]['total_price'] = round($lineSubtot + $taxAmount, 2);

            $subtotal += $lineSubtot;
        }

        $taxAmount = round(array_sum(array_column($items, 'tax_amount')), 2);

        return [
            'items'        => $items,
            'subtotal'     => round($subtotal, 2),
            'tax_amount'   => $taxAmount,
            'total_amount' => round($subtotal + $taxAmount, 2),
        ];
    }

    protected function syncExpenseItems(PurchaseOrderModel $order, array $items): void
    {
        $order->expenseItems()->delete();

        foreach ($items as $item) {
            ExpenseOrderItemModel::create([
                'purchase_order_id'  => $order->id,
                'description'        => $item['description'],
                'unit'               => $item['unit'] ?? 'und',
                'quantity_requested' => $item['quantity'],
                'unit_price'         => $item['unit_price'],
                'tax_rate'           => $item['tax_rate'],
                'tax_amount'         => $item['tax_amount'],
                'total_price'        => $item['total_price'],
                'notes'              => $item['notes'] ?? null,
            ]);
        }
    }
}
