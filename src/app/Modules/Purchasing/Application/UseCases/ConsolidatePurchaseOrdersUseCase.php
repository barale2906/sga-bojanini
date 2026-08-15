<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Application\UseCases;

use App\Modules\Purchasing\Domain\Enums\PurchaseOrderStatus;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\ConsolidatedPurchaseOrderItemModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\ConsolidatedPurchaseOrderModel;
use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use Illuminate\Support\Facades\DB;

class ConsolidatePurchaseOrdersUseCase
{
    public function execute(array $orderIds, int $userId, ?string $notes): ConsolidatedPurchaseOrderModel
    {
        return DB::transaction(function () use ($orderIds, $userId, $notes) {
            $orders = PurchaseOrderModel::with('items')
                ->whereIn('id', $orderIds)
                ->lockForUpdate()
                ->get();

            $this->validate($orders, $orderIds);

            $supplierId = $orders->first()->supplier_id;
            $periodFrom = $orders->min('created_at')->toDateString();
            $periodTo   = $orders->max('created_at')->toDateString();

            $consolidated = ConsolidatedPurchaseOrderModel::create([
                'code'        => $this->generateCode(),
                'supplier_id' => $supplierId,
                'period_from' => $periodFrom,
                'period_to'   => $periodTo,
                'notes'       => $notes,
                'created_by'  => $userId,
                'subtotal'    => 0,
                'tax_amount'  => 0,
                'total_amount' => 0,
            ]);

            $this->buildConsolidatedItems($consolidated, $orders);

            $orders->each(fn ($order) => $order->update(['consolidated_order_id' => $consolidated->id]));

            return $consolidated->load(['items.variant.genericProduct', 'items.presentation', 'supplier', 'purchaseOrders']);
        });
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

    private function buildConsolidatedItems(ConsolidatedPurchaseOrderModel $consolidated, \Illuminate\Database\Eloquent\Collection $orders): void
    {
        $grouped = [];

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
                    ];
                }

                $grouped[$key]['quantity'] += (float) $item->quantity_received;
            }
        }

        $subtotal    = 0.0;
        $totalTax    = 0.0;

        foreach ($grouped as $line) {
            $lineSubtotal = round($line['quantity'] * $line['unit_price'], 2);
            $lineTax      = round($lineSubtotal * $line['tax_rate'] / 100, 2);
            $lineTotal    = round($lineSubtotal + $lineTax, 2);

            ConsolidatedPurchaseOrderItemModel::create([
                'consolidated_order_id'   => $consolidated->id,
                'product_variant_id'      => $line['product_variant_id'],
                'product_presentation_id' => $line['product_presentation_id'],
                'quantity'                => $line['quantity'],
                'unit_price'              => $line['unit_price'],
                'tax_rate'                => $line['tax_rate'],
                'tax_amount'              => $lineTax,
                'total_price'             => $lineTotal,
            ]);

            $subtotal += $lineSubtotal;
            $totalTax += $lineTax;
        }

        $consolidated->update([
            'subtotal'     => round($subtotal, 2),
            'tax_amount'   => round($totalTax, 2),
            'total_amount' => round($subtotal + $totalTax, 2),
        ]);
    }

    private function generateCode(): string
    {
        $year   = now()->format('Y');
        $prefix = "OCC-{$year}-";

        $last = ConsolidatedPurchaseOrderModel::where('code', 'like', $prefix.'%')
            ->orderByDesc('code')
            ->value('code');

        $next = $last !== null ? (int) substr($last, -5) + 1 : 1;

        return $prefix.str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
