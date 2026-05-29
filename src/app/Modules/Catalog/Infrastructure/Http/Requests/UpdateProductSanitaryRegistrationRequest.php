<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductSanitaryRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'registration_number' => ['required', 'string', 'max:100'],
            'expiry_date'         => ['required', 'date_format:Y-m-d'],
            'is_active'           => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'registration_number.required' => 'El número de registro sanitario es obligatorio.',
            'registration_number.max'      => 'El número de registro no puede superar 100 caracteres.',
            'expiry_date.required'         => 'La fecha de vencimiento es obligatoria.',
            'expiry_date.date_format'      => 'La fecha de vencimiento debe tener el formato YYYY-MM-DD.',
        ];
    }
}
