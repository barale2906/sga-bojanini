<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReadingRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'value'          => ['required', 'numeric'],
            'reading_source' => ['required', 'in:manual,iot'],
            'recorded_at'    => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'value.required'          => 'El valor de la lectura es obligatorio.',
            'value.numeric'           => 'El valor debe ser numérico.',
            'reading_source.required' => 'La fuente de lectura es obligatoria.',
            'reading_source.in'       => 'La fuente debe ser "manual" o "iot".',
            'recorded_at.required'    => 'La fecha de registro es obligatoria.',
            'recorded_at.date'        => 'La fecha debe ser válida.',
        ];
    }
}
