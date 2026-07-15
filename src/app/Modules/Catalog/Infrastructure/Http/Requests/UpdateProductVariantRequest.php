<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lab_brand'               => ['required', 'string', 'max:255'],
            'brand_sku'               => ['nullable', 'string', 'max:100'],
            'commercial_presentation' => ['nullable', 'string', 'max:150'],
            'serie_reference'         => ['nullable', 'string', 'max:150'],
            'useful_life'             => ['nullable', 'string', 'max:100'],
            'risk_level'              => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'lab_brand.required' => 'El laboratorio/marca es obligatorio.',
        ];
    }
}
