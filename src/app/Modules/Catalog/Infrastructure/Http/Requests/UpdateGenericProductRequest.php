<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGenericProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id'         => ['required', 'integer', 'exists:categories,id'],
            'classification_id'   => ['nullable', 'integer', 'exists:product_classifications,id'],
            'base_unit_id'        => ['required', 'integer', 'exists:units_of_measure,id'],
            'product_type'        => ['nullable', 'string', 'in:simple,kit'],
            'name'                => ['required', 'string', 'max:255'],
            'description'         => ['nullable', 'string'],
            'concentration'       => ['nullable', 'string', 'max:100'],
            'pharmaceutical_form' => ['nullable', 'string', 'max:150'],
            'volume_cm3'          => ['nullable', 'numeric', 'min:0'],
            'weight_kg'           => ['nullable', 'numeric', 'min:0'],
            'requires_cold_chain' => ['nullable', 'boolean'],
            'reorder_point'       => ['nullable', 'integer', 'min:0'],
            'reorder_quantity'    => ['nullable', 'integer', 'min:0'],
            'min_stock'           => ['nullable', 'integer', 'min:0'],
            'max_stock'           => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required'  => 'La categoría es obligatoria.',
            'category_id.exists'    => 'La categoría seleccionada no existe.',
            'base_unit_id.required' => 'La unidad de medida base es obligatoria.',
            'name.required'         => 'El nombre del producto genérico es obligatorio.',
        ];
    }
}
