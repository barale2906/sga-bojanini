<?php

declare(strict_types=1);

namespace App\Modules\Integration\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsumptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'appointment_id'     => ['nullable', 'string', 'max:100'],
            'patient_identifier' => ['nullable', 'string', 'max:100'],
            'service_type'       => ['nullable', 'string', 'max:255'],
            'warehouse_id'       => ['required', 'integer', 'exists:warehouses,id'],
            'cost_center_id'     => ['required', 'integer', 'exists:cost_centers,id'],
            'service_id'         => ['nullable', 'integer', 'exists:medical_services,id'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
        ];
    }
}
