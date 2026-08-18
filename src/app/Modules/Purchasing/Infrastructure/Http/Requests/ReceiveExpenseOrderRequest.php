<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveExpenseOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items'                       => ['required', 'array', 'min:1'],
            'items.*.item_id'             => ['required', 'integer'],
            'items.*.quantity_received'   => ['required', 'numeric', 'min:0.001'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'                     => 'Debe indicar al menos un ítem a recibir.',
            'items.*.item_id.required'           => 'El ID del ítem es obligatorio.',
            'items.*.quantity_received.required' => 'La cantidad recibida es obligatoria.',
            'items.*.quantity_received.min'      => 'La cantidad recibida debe ser mayor a cero.',
        ];
    }
}
