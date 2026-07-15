<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClinicalTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'     => ['required', 'string', 'max:200'],
            'content'   => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'   => 'El título de la plantilla es obligatorio.',
            'content.required' => 'El contenido de la plantilla es obligatorio.',
        ];
    }
}
