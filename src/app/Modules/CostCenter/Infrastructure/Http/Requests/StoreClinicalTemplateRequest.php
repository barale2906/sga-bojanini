<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClinicalTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medical_service_id' => ['required', 'integer', 'exists:medical_services,id'],
            'title'              => ['required', 'string', 'max:200'],
            'content'            => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'medical_service_id.required' => 'El servicio o procedimiento es obligatorio.',
            'medical_service_id.exists'   => 'El servicio o procedimiento no existe.',
            'title.required'              => 'El título de la plantilla es obligatorio.',
            'content.required'            => 'El contenido de la plantilla es obligatorio.',
        ];
    }
}
