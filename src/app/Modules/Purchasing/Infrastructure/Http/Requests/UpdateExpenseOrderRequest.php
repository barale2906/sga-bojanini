<?php

declare(strict_types=1);

namespace App\Modules\Purchasing\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'supplier_id'              => ['required', 'integer', 'exists:suppliers,id', new \App\Rules\ExpenseSupplierRule()],
            'expected_delivery_date'   => ['nullable', 'date'],
            'notes'                    => ['nullable', 'string', 'max:500'],
            'items'                    => ['required', 'array', 'min:1'],
            'items.*.description'      => ['required', 'string', 'max:255'],
            'items.*.unit'             => ['required', 'string', 'max:50'],
            'items.*.quantity'         => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_price'       => ['required', 'numeric', 'min:0'],
            'items.*.tax_rate'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.notes'            => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required'         => 'El proveedor es obligatorio.',
            'supplier_id.exists'           => 'El proveedor seleccionado no existe.',
            'items.required'               => 'Debe incluir al menos un ítem.',
            'items.min'                    => 'Debe incluir al menos un ítem.',
            'items.*.description.required' => 'La descripción del ítem es obligatoria.',
            'items.*.unit.required'        => 'La unidad del ítem es obligatoria.',
            'items.*.quantity.required'    => 'La cantidad es obligatoria.',
            'items.*.quantity.min'         => 'La cantidad debe ser mayor a cero.',
            'items.*.unit_price.required'  => 'El precio unitario es obligatorio.',
            'items.*.unit_price.min'       => 'El precio no puede ser negativo.',
        ];
    }
}
