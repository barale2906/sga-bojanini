<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password'],
            'new_password'     => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'new_password.min'                  => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'new_password.confirmed'            => 'La confirmación de contraseña no coincide.',
        ];
    }
}
