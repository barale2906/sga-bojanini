<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id'            => ['required', 'integer', 'exists:warehouses,id'],
            'cost_center_id'          => ['required', 'integer', 'exists:cost_centers,id'],
            'service_id'              => ['nullable', 'integer', 'exists:medical_services,id'],
            'patient_document'        => ['nullable', 'string', 'max:50'],
            'patient_external_id'     => ['nullable', 'string', 'max:100'],
            'reason'                  => ['nullable', 'string'],

            'items'                   => ['required', 'array', 'min:1'],
            'items.*.generic_product_id' => ['required', 'integer', 'exists:product_generics,id'],
            'items.*.quantity'           => ['required', 'integer', 'min:1'],
            'items.*.location_id'        => ['nullable', 'integer', 'exists:locations,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required'                       => 'Debe incluir al menos un producto.',
            'items.min'                            => 'Debe incluir al menos un producto.',
            'items.*.generic_product_id.required'  => 'El producto es obligatorio en cada ítem.',
            'items.*.generic_product_id.exists'    => 'Uno de los productos no existe.',
            'items.*.quantity.required'            => 'La cantidad es obligatoria en cada ítem.',
            'items.*.quantity.min'                 => 'La cantidad debe ser mayor a cero en cada ítem.',
        ];
    }
}
