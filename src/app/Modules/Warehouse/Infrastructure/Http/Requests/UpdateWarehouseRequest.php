<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('warehouse');

        return [
            'name'        => ['required', 'string', 'max:255'],
            'code'        => ['nullable', 'string', 'max:50', "unique:warehouses,code,{$id}"],
            'address'     => ['nullable', 'string'],
            'description' => ['nullable', 'string'],

            'zones'                              => ['nullable', 'array'],
            'zones.*.id'                         => ['nullable', 'integer', 'exists:zones,id'],
            'zones.*.name'                       => ['required', 'string', 'max:255'],
            'zones.*.code'                       => ['nullable', 'string', 'max:50'],
            'zones.*.type'                       => ['required', 'in:ambient,cold,frozen,controlled'],
            'zones.*.temp_min'                   => ['nullable', 'numeric'],
            'zones.*.temp_max'                   => ['nullable', 'numeric'],
            'zones.*.humidity_min'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'zones.*.humidity_max'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'zones.*.description'                => ['nullable', 'string'],
            'zones.*.locations'                  => ['nullable', 'array'],
            'zones.*.locations.*.id'             => ['nullable', 'integer', 'exists:locations,id'],
            'zones.*.locations.*.name'           => ['required', 'string', 'max:255'],
            'zones.*.locations.*.code'           => ['nullable', 'string', 'max:50'],
            'zones.*.locations.*.volume_cm3'     => ['nullable', 'numeric', 'min:0'],
            'zones.*.locations.*.max_weight_kg'  => ['nullable', 'numeric', 'min:0'],
            'zones.*.locations.*.description'    => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'          => 'El nombre del almacén es obligatorio.',
            'code.unique'            => 'Ya existe un almacén con este código.',
            'zones.*.id.exists'      => 'La zona indicada no existe.',
            'zones.*.name.required'  => 'El nombre de cada zona es obligatorio.',
            'zones.*.type.required'  => 'El tipo de cada zona es obligatorio.',
            'zones.*.type.in'        => 'El tipo de zona debe ser: ambient, cold, frozen o controlled.',
            'zones.*.locations.*.id.exists'   => 'La ubicación indicada no existe.',
            'zones.*.locations.*.name.required' => 'El nombre de cada ubicación es obligatorio.',
        ];
    }
}
