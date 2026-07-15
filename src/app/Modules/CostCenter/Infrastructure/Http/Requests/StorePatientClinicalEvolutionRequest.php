<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientClinicalEvolutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content'     => ['required', 'string'],
            'recorded_at' => ['sometimes', 'date_format:Y-m-d H:i:s'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.required'        => 'El contenido de la evolución es obligatorio.',
            'recorded_at.date_format' => 'La fecha de registro debe tener el formato Y-m-d H:i:s.',
        ];
    }
}
