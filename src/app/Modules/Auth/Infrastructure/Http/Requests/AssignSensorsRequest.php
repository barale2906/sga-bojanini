<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignSensorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sensor_ids' => ['present', 'array'],
            'sensor_ids.*' => ['integer', 'exists:sensors,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'sensor_ids.present'   => 'El campo sensor_ids es obligatorio.',
            'sensor_ids.array'     => 'El campo sensor_ids debe ser un arreglo.',
            'sensor_ids.*.integer' => 'Cada sensor debe ser un identificador válido.',
            'sensor_ids.*.exists'  => 'Uno o más sensores no existen.',
        ];
    }
}
