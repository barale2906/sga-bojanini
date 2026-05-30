<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachSupplierProductByCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id'    => ['required', 'integer', 'exists:categories,id'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'unit_price'     => ['nullable', 'numeric', 'min:0'],
            'is_preferred'   => ['nullable', 'boolean'],
        ];
    }
}
