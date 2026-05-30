<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicalServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code'        => ['required', 'string', 'max:20', 'unique:medical_services,code'],
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
