<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductPresentationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id'           => ['nullable', 'integer', 'exists:product_presentations,id'],
            'name'                => ['required', 'string', 'max:255'],
            'code'                => ['required', 'string', 'max:50', 'unique:product_presentations,code'],
            'units_of_measure_id' => ['required', 'integer', 'exists:units_of_measure,id'],
            'quantity_per_parent' => ['nullable', 'integer', 'min:1'],
            'factor_to_base'      => ['required', 'integer', 'min:1'],
            'level'               => ['required', 'integer', 'min:1'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
        ];
    }
}
