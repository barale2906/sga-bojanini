<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBulkReadingsRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'readings'               => ['required', 'array', 'min:1', 'max:500'],
            'readings.*.sensor_code' => ['required', 'string', 'max:50'],
            'readings.*.value'       => ['required', 'numeric'],
            'readings.*.recorded_at' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'readings.required'               => 'Debe enviar al menos una lectura.',
            'readings.array'                  => 'El campo readings debe ser un array.',
            'readings.max'                    => 'Máximo 500 lecturas por lote.',
            'readings.*.sensor_code.required' => 'Cada lectura requiere un código de sensor.',
            'readings.*.value.required'       => 'Cada lectura requiere un valor.',
            'readings.*.value.numeric'        => 'El valor debe ser numérico.',
            'readings.*.recorded_at.required' => 'Cada lectura requiere una fecha.',
        ];
    }
}
