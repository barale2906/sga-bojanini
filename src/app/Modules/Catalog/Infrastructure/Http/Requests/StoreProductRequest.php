<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id'            => ['required', 'integer', 'exists:categories,id'],
            'classification_id'      => ['nullable', 'integer', 'exists:product_classifications,id'],
            'base_unit_id'           => ['required', 'integer', 'exists:units_of_measure,id'],
            'product_type'           => ['nullable', 'string', 'in:simple,kit'],
            'components'             => ['nullable', 'array', 'required_if:product_type,kit'],
            'components.*.component_product_id' => ['required_with:components', 'integer', 'exists:products,id'],
            'components.*.quantity_per_kit'     => ['required_with:components', 'integer', 'min:1'],
            'name'                   => ['required', 'string', 'max:255'],
            'code'                   => ['required', 'string', 'max:50', 'unique:products,code'],
            'sku'                    => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'description'            => ['nullable', 'string'],
            'volume_cm3'             => ['nullable', 'numeric', 'min:0'],
            'weight_kg'              => ['nullable', 'numeric', 'min:0'],
            'requires_cold_chain'    => ['nullable', 'boolean'],
            'reorder_point'          => ['nullable', 'integer', 'min:0'],
            'reorder_quantity'       => ['nullable', 'integer', 'min:0'],
            'min_stock'              => ['nullable', 'integer', 'min:0'],
            'max_stock'              => ['nullable', 'integer', 'min:0'],
            'concentration'          => ['nullable', 'string', 'max:100'],
            'risk_level'             => ['nullable', 'string', 'max:100'],
            'lab_brand'              => ['nullable', 'string', 'max:255'],
            'pharmaceutical_form'    => ['nullable', 'string', 'max:150'],
            'commercial_presentation'=> ['nullable', 'string', 'max:150'],
            'serie_reference'        => ['nullable', 'string', 'max:150'],
            'useful_life'            => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required'          => 'La categoría es obligatoria.',
            'category_id.exists'            => 'La categoría seleccionada no existe.',
            'classification_id.exists'      => 'La clasificación seleccionada no existe.',
            'base_unit_id.required'         => 'La unidad de medida base es obligatoria.',
            'base_unit_id.exists'           => 'La unidad de medida base seleccionada no existe.',
            'product_type.in'               => 'El tipo de producto debe ser simple o kit.',
            'name.required'                 => 'El nombre del producto es obligatorio.',
            'code.required'                 => 'El código del producto es obligatorio.',
            'code.unique'                   => 'Ya existe un producto con este código.',
            'sku.unique'                    => 'Ya existe un producto con este SKU.',
            'volume_cm3.min'                => 'El volumen no puede ser negativo.',
            'weight_kg.min'                 => 'El peso no puede ser negativo.',
            'concentration.max'             => 'La concentración no puede superar 100 caracteres.',
            'risk_level.max'                => 'El nivel de riesgo no puede superar 100 caracteres.',
            'lab_brand.max'                 => 'El laboratorio/marca no puede superar 255 caracteres.',
            'pharmaceutical_form.max'       => 'La forma farmacéutica no puede superar 150 caracteres.',
            'commercial_presentation.max'   => 'La presentación comercial no puede superar 150 caracteres.',
            'serie_reference.max'           => 'La serie/referencia no puede superar 150 caracteres.',
            'useful_life.max'               => 'La vida útil no puede superar 100 caracteres.',
        ];
    }
}
