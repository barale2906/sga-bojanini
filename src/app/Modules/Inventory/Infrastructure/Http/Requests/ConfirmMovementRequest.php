<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivered_by'            => ['required', 'array'],
            'delivered_by.name'       => ['required', 'string', 'max:150'],
            'delivered_by.document'   => ['required', 'string', 'max:50'],
            'delivered_by.signature'  => ['required', 'string'],

            'received_by'             => ['required', 'array'],
            'received_by.name'        => ['required', 'string', 'max:150'],
            'received_by.document'    => ['required', 'string', 'max:50'],
            'received_by.signature'   => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'delivered_by.required'           => 'Los datos de quien entrega son obligatorios.',
            'delivered_by.name.required'      => 'El nombre de quien entrega es obligatorio.',
            'delivered_by.document.required'  => 'El documento de quien entrega es obligatorio.',
            'delivered_by.signature.required' => 'La firma de quien entrega es obligatoria.',
            'received_by.required'            => 'Los datos de quien recibe son obligatorios.',
            'received_by.name.required'       => 'El nombre de quien recibe es obligatorio.',
            'received_by.document.required'   => 'El documento de quien recibe es obligatorio.',
            'received_by.signature.required'  => 'La firma de quien recibe es obligatoria.',
        ];
    }

    public function signatures(): array
    {
        $validated = $this->validated();

        return [
            'delivered_by' => [
                'name'      => $validated['delivered_by']['name'],
                'document'  => $validated['delivered_by']['document'],
                'signature' => $validated['delivered_by']['signature'],
            ],
            'received_by' => [
                'name'      => $validated['received_by']['name'],
                'document'  => $validated['received_by']['document'],
                'signature' => $validated['received_by']['signature'],
            ],
        ];
    }
}
