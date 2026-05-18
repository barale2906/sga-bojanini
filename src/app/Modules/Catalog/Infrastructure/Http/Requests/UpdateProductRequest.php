<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('product');

        return [
            'category_id'         => ['required', 'integer', 'exists:categories,id'],
            'base_unit_id'        => ['required', 'integer', 'exists:units_of_measure,id'],
            'product_type'        => ['nullable', 'string', 'in:simple,kit'],
            'product_type'        => ['nullable', 'string', 'in:simple,kit'],
            'name'                => ['required', 'string', 'max:255'],
            'code'                => ['required', 'string', 'max:50', "unique:products,code,{$id}"],
            'sku'                 => ['nullable', 'string', 'max:100', "unique:products,sku,{$id}"],
            'description'         => ['nullable', 'string'],
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
            'category_id.required'        => 'La categoría es obligatoria.',
            'category_id.exists'          => 'La categoría seleccionada no existe.',
            'base_unit_id.required' => 'La unidad de medida base es obligatoria.',
            'base_unit_id.exists'   => 'La unidad de medida base seleccionada no existe.',
            'product_type.in'             => 'El tipo de producto debe ser simple o kit.',
            'name.required'               => 'El nombre del producto es obligatorio.',
            'code.required'                 => 'El código del producto es obligatorio.',
            'code.unique'                   => 'Ya existe un producto con este código.',
            'sku.unique'                    => 'Ya existe un producto con este SKU.',
        ];
    }
}
