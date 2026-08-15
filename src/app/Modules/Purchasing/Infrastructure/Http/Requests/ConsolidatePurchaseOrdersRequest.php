<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsolidatePurchaseOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_order_ids'   => ['required', 'array', 'min:1'],
            'purchase_order_ids.*' => ['required', 'integer', 'exists:purchase_orders,id'],
            'notes'                => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'purchase_order_ids.required' => 'Debe seleccionar al menos una orden de compra.',
            'purchase_order_ids.min'      => 'Debe seleccionar al menos una orden de compra.',
            'purchase_order_ids.*.exists' => 'Una o más órdenes de compra no existen.',
        ];
    }
}
