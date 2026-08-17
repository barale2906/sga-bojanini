<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendDocumentEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipients'   => ['required', 'array', 'min:1'],
            'recipients.*' => ['required', 'email'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipients.required'   => 'Debe indicar al menos un destinatario.',
            'recipients.min'        => 'Debe indicar al menos un destinatario.',
            'recipients.*.required' => 'Cada destinatario debe tener un correo.',
            'recipients.*.email'    => 'Uno de los correos ingresados no tiene un formato válido.',
        ];
    }
}
