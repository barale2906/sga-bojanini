<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parent_id'   => ['nullable', 'integer', 'exists:categories,id'],
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['required', 'string', 'max:50', 'unique:categories,code'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'parent_id.exists' => 'La categoría padre seleccionada no existe.',
            'name.required'    => 'El nombre de la categoría es obligatorio.',
            'code.required'    => 'El código de la categoría es obligatorio.',
            'code.unique'      => 'Ya existe una categoría con este código.',
            'code.max'         => 'El código no puede tener más de 50 caracteres.',
        ];
    }
}
