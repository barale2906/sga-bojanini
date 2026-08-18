<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendToAccountingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'recipients'    => ['required', 'array', 'min:1'],
            'recipients.*'  => ['required', 'email'],
            'attachments'   => ['sometimes', 'array'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,xlsx,xls,doc,docx'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipients.required'    => 'Debe indicar al menos un destinatario.',
            'recipients.min'         => 'Debe indicar al menos un destinatario.',
            'recipients.*.email'     => 'Uno o más destinatarios no tienen un correo válido.',
            'attachments.*.file'     => 'Uno o más adjuntos no son archivos válidos.',
            'attachments.*.max'      => 'Cada adjunto no puede superar los 10 MB.',
            'attachments.*.mimes'    => 'Los adjuntos deben ser PDF, imágenes, Excel o Word.',
        ];
    }
}
