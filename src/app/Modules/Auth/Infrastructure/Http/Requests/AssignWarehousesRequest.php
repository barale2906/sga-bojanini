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
            'warehouse_ids'   => ['required', 'array'],
            'warehouse_ids.*' => ['integer', 'exists:warehouses,id'],
        ];
    }
}
