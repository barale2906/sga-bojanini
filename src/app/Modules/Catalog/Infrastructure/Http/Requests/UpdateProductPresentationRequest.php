<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductPresentationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = (int) $this->route('presentation');

        return [
            'parent_id'           => ['nullable', 'integer', 'exists:product_presentations,id'],
            'name'                => ['sometimes', 'string', 'max:255'],
            'code'                => ['sometimes', 'string', 'max:50', Rule::unique('product_presentations', 'code')->ignore($id)],
            'units_of_measure_id' => ['sometimes', 'integer', 'exists:units_of_measure,id'],
            'quantity_per_parent' => ['nullable', 'integer', 'min:1'],
            'factor_to_base'      => ['sometimes', 'integer', 'min:1'],
            'level'               => ['sometimes', 'integer', 'min:1'],
            'is_active'           => ['nullable', 'boolean'],
            'sort_order'          => ['nullable', 'integer', 'min:0'],
        ];
    }
}
