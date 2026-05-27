<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'zone_id'       => ['required', 'integer', 'exists:zones,id'],
            'name'          => ['required', 'string', 'max:255'],
            'code'          => ['required', 'string', 'max:50'],
            'volume_cm3'    => ['nullable', 'numeric', 'min:0'],
            'max_weight_kg' => ['nullable', 'numeric', 'min:0'],
            'description'   => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'zone_id.required'    => 'La zona es obligatoria.',
            'zone_id.exists'      => 'La zona indicada no existe.',
            'name.required'       => 'El nombre de la ubicación es obligatorio.',
            'code.required'       => 'El código de la ubicación es obligatorio.',
            'volume_cm3.min'      => 'El volumen no puede ser negativo.',
            'max_weight_kg.min'   => 'El peso máximo no puede ser negativo.',
        ];
    }
}
