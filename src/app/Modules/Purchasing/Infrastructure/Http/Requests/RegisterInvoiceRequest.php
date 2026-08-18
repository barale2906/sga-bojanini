<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterInvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'invoice_number' => ['required', 'string', 'max:100'],
            'invoice_date'   => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_number.required' => 'El número de factura es obligatorio.',
            'invoice_date.required'   => 'La fecha de la factura es obligatoria.',
        ];
    }
}
