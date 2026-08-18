<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Resources;

use App\Modules\Purchasing\Infrastructure\Persistence\Models\ExpenseOrderItemModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExpenseOrderItemModel */
class ExpenseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'description'        => $this->description,
            'unit'               => $this->unit,
            'quantity_requested' => $this->quantity_requested,
            'quantity_received'  => $this->quantity_received,
            'unit_price'         => $this->unit_price,
            'tax_rate'           => $this->tax_rate,
            'tax_amount'         => $this->tax_amount,
            'total_price'        => $this->total_price,
            'notes'              => $this->notes,
        ];
    }
}
