<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductClassificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'                      => ['required', 'string', 'max:20', 'unique:product_classifications,code'],
            'name'                      => ['required', 'string', 'max:100'],
            'description'               => ['nullable', 'string'],
            'has_sanitary_registration' => ['nullable', 'boolean'],
            'has_concentration'         => ['nullable', 'boolean'],
            'has_risk_level'            => ['nullable', 'boolean'],
            'has_pharma_fields'         => ['nullable', 'boolean'],
            'has_device_fields'         => ['nullable', 'boolean'],
            'has_lab_brand'             => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'El código de clasificación es obligatorio.',
            'code.max'      => 'El código no puede superar 20 caracteres.',
            'code.unique'   => 'Ya existe una clasificación con este código.',
            'name.required' => 'El nombre de la clasificación es obligatorio.',
            'name.max'      => 'El nombre no puede superar 100 caracteres.',
        ];
    }
}
