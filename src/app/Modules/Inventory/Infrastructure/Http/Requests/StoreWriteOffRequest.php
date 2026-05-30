<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWriteOffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'batch_id'     => ['required', 'integer', 'exists:batches,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'location_id'  => ['nullable', 'integer', 'exists:locations,id'],
            'reason'       => ['nullable', 'string'],
        ];
    }
}
