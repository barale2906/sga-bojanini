<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Resources;

use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrderModel */
class ExpenseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'order_type'             => $this->order_type,
            'code'                   => $this->code,
            'status'                 => $this->status,
            'payment_status'         => $this->payment_status,
            'supplier_id'            => $this->supplier_id,
            'subtotal'               => $this->subtotal,
            'tax_amount'             => $this->tax_amount,
            'total_amount'           => $this->total_amount,
            'amount_paid'            => $this->amount_paid,
            'invoice_number'         => $this->invoice_number,
            'invoice_date'           => $this->invoice_date?->format('Y-m-d'),
            'notes'                  => $this->notes,
            'expected_delivery_date' => $this->expected_delivery_date?->format('Y-m-d'),
            'created_by'             => $this->created_by,
            'sent_at'                => $this->sent_at?->toIso8601String(),
            'received_at'            => $this->received_at?->toIso8601String(),
            'accounting_sent_at'     => $this->accounting_sent_at?->toIso8601String(),
            'created_at'             => $this->created_at?->toIso8601String(),
            'updated_at'             => $this->updated_at?->toIso8601String(),
            'supplier' => $this->whenLoaded('supplier', fn () => [
                'id'    => $this->supplier->id,
                'name'  => $this->supplier->name,
                'email' => $this->supplier->email,
            ]),
            'items'    => ExpenseOrderItemResource::collection($this->whenLoaded('expenseItems')),
            'payments' => PurchaseOrderPaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
