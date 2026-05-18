<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items'                      => ['required', 'array', 'min:1'],
            'items.*.item_id'            => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.quantity_received'  => ['required', 'integer', 'min:1'],
            'items.*.lot_number'         => ['required', 'string', 'max:100'],
            'items.*.expiration_date'    => ['required', 'date'],
            'items.*.location_id'        => ['required', 'integer', 'exists:locations,id'],
        ];
    }
}
