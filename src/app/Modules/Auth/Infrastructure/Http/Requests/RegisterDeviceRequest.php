<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Registro del token push (FCM) del dispositivo del usuario autenticado.
 */
class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token'       => ['required', 'string', 'max:255'],
            'platform'    => ['required', 'string', 'in:android,ios'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required'    => 'El token del dispositivo es obligatorio.',
            'platform.required' => 'La plataforma del dispositivo es obligatoria.',
            'platform.in'       => 'La plataforma debe ser android o ios.',
        ];
    }
}
