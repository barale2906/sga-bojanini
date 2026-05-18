<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'       => ['required', 'integer', 'exists:products,id'],
            'warehouse_id'     => ['required', 'integer', 'exists:warehouses,id'],
            'location_from_id' => ['required', 'integer', 'exists:locations,id'],
            'location_to_id'   => ['required', 'integer', 'exists:locations,id', 'different:location_from_id'],
            'quantity'         => ['required', 'integer', 'min:1'],
            'reason'           => ['nullable', 'string'],
        ];
    }
}
