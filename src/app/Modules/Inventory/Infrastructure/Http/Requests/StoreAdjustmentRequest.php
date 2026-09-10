<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'warehouse_id'       => ['required', 'integer', 'exists:warehouses,id'],
            'location_id'        => ['required', 'integer', 'exists:locations,id'],
            'batch_id'           => ['nullable', 'integer', 'exists:batches,id'],
            'quantity'           => ['required', 'numeric', 'not_in:0'],
            'reason'             => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'batch_id.exists' => 'El lote indicado no existe.',
        ];
    }
}
