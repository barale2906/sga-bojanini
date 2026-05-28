<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'            => ['required', 'integer', 'exists:suppliers,id'],
            'warehouse_id'           => ['required', 'integer', 'exists:warehouses,id'],
            'notes'                  => ['nullable', 'string'],
            'expected_delivery_date' => ['nullable', 'date'],
            'items'                  => ['required', 'array', 'min:1'],
            'items.*.product_id'              => ['required', 'integer', 'exists:products,id'],
            'items.*.product_presentation_id' => ['required', 'integer', 'exists:product_presentations,id'],
            'items.*.quantity'                => ['required', 'integer', 'min:1'],
            'items.*.unit_price'              => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate'                => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.notes'                   => ['nullable', 'string'],
        ];
    }
}
