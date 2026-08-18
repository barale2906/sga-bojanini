<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'payment_date'   => ['required', 'date'],
            'payment_method' => ['required', 'string', 'in:cash,transfer,check,other'],
            'reference'      => ['nullable', 'string', 'max:100'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required'         => 'El monto es obligatorio.',
            'amount.min'              => 'El monto debe ser mayor a cero.',
            'payment_date.required'   => 'La fecha de pago es obligatoria.',
            'payment_method.required' => 'El método de pago es obligatorio.',
            'payment_method.in'       => 'Método de pago inválido. Use: cash, transfer, check, other.',
        ];
    }
}
