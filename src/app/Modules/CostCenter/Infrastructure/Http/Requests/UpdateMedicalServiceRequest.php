<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMedicalServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = (int) $this->route('medical_service');

        return [
            'type'        => ['sometimes', 'string', Rule::in(['service', 'procedure'])],
            'parent_id'   => ['nullable', 'integer', Rule::exists('medical_services', 'id')->whereNot('id', $id)],
            'code'        => ['required', 'string', 'max:20', Rule::unique('medical_services', 'code')->ignore($id)],
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.exists' => 'El servicio padre seleccionado no existe o es el mismo registro.',
            'type.in'          => 'El tipo debe ser "service" (servicio) o "procedure" (procedimiento).',
        ];
    }
}
