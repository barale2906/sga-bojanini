<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMedicalServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'        => ['sometimes', 'string', Rule::in(['service', 'procedure'])],
            'parent_id'   => ['nullable', 'integer', 'exists:medical_services,id'],
            'code'        => ['required', 'string', 'max:20', 'unique:medical_services,code'],
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.exists' => 'El servicio padre seleccionado no existe.',
            'type.in'          => 'El tipo debe ser "service" (servicio) o "procedure" (procedimiento).',
        ];
    }
}
