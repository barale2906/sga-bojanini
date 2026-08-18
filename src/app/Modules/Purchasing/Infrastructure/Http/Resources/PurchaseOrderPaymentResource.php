<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Resources;

use App\Modules\Purchasing\Infrastructure\Persistence\Models\PurchaseOrderPaymentModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PurchaseOrderPaymentModel */
class PurchaseOrderPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'amount'         => $this->amount,
            'payment_date'   => $this->payment_date?->format('Y-m-d'),
            'payment_method' => $this->payment_method,
            'reference'      => $this->reference,
            'notes'          => $this->notes,
            'registered_by'  => $this->whenLoaded('registeredBy', fn () => [
                'id'   => $this->registeredBy->id,
                'name' => $this->registeredBy->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
