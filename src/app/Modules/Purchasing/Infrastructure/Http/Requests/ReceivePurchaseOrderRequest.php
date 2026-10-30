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
            'items.*.expiration_date'    => ['required', 'date', 'after_or_equal:2000-01-01', 'before:2100-01-01'],
            'items.*.location_id'        => ['required', 'integer', 'exists:locations,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.expiration_date.after_or_equal' => 'La fecha de vencimiento tiene un año inválido; use el año completo (ej. 2028).',
            'items.*.expiration_date.before'         => 'La fecha de vencimiento tiene un año inválido; use el año completo (ej. 2028).',
        ];
    }
}
