<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_sku'            => ['nullable', 'string', 'max:100'],
            'product_presentation_id' => ['nullable', 'integer', 'exists:product_presentations,id'],
            'lead_time_days'          => ['nullable', 'integer', 'min:0'],
            'unit_price'              => ['nullable', 'numeric', 'min:0'],
            'is_preferred'            => ['nullable', 'boolean'],
        ];
    }
}
