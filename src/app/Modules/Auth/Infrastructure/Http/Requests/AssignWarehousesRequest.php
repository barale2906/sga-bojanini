<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignWarehousesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_ids'   => ['present', 'array'],
            'warehouse_ids.*' => ['integer', 'exists:warehouses,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_ids.present'    => 'El campo warehouse_ids es obligatorio.',
            'warehouse_ids.array'      => 'El campo warehouse_ids debe ser un arreglo.',
            'warehouse_ids.*.integer'  => 'Cada almacén debe ser un identificador válido.',
            'warehouse_ids.*.exists'   => 'Uno o más almacenes no existen.',
        ];
    }
}
