<?php

declare(strict_types=1);

namespace App\Modules\Warehouse\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id'  => ['required', 'integer', 'exists:warehouses,id'],
            'name'          => ['required', 'string', 'max:255'],
            'code'          => ['required', 'string', 'max:50'],
            'type'          => ['required', 'in:ambient,cold,frozen,controlled'],
            'temp_min'      => ['nullable', 'numeric'],
            'temp_max'      => ['nullable', 'numeric', 'gte:temp_min'],
            'humidity_min'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'humidity_max'  => ['nullable', 'numeric', 'gte:humidity_min', 'max:100'],
            'description'   => ['nullable', 'string'],
        ];
    }
}
