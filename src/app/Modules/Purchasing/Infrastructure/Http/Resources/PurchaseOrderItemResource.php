<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Resources;

use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderItemModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrderItemModel */
class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'product_id'              => $this->product_id,
            'product_presentation_id' => $this->product_presentation_id,
            'quantity_requested'      => $this->quantity_requested,
            'quantity_requested_base' => $this->quantity_requested_base,
            'quantity_received'       => $this->quantity_received,
            'quantity_received_base'  => $this->quantity_received_base,
            'unit_price'              => $this->unit_price,
            'tax_rate'                => $this->tax_rate,
            'tax_amount'              => $this->tax_amount,
            'total_price'             => $this->total_price,
            'notes'                   => $this->notes,
            'product'                 => $this->whenLoaded('product', fn () => [
                'id'   => $this->product->id,
                'code' => $this->product->code,
                'name' => $this->product->name,
            ]),
            'presentation' => $this->whenLoaded('presentation', fn () => [
                'id'   => $this->presentation->id,
                'code' => $this->presentation->code,
                'name' => $this->presentation->name,
            ]),
        ];
    }
}
