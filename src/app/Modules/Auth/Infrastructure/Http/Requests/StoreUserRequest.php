<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255', 'unique:users,email'],
            'password'     => ['required', 'string', 'min:8'],
            'phone'        => ['nullable', 'string', 'max:20'],
            'is_active'    => ['nullable', 'boolean'],
            'role_ids'     => ['nullable', 'array'],
            'role_ids.*'   => ['integer', 'exists:roles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'El nombre es obligatorio.',
            'email.required'    => 'El correo es obligatorio.',
            'email.unique'      => 'Ya existe un usuario con este correo.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min'      => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}
