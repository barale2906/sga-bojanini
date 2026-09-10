<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_external_id' => ['required', 'string', 'max:100'],
            'patient_document'    => ['required', 'string', 'max:50'],
            'patient_first_name'  => ['required', 'string', 'max:100'],
            'patient_last_name'   => ['required', 'string', 'max:100'],
            'patient_email'       => ['nullable', 'email', 'max:100'],
            'patient_address'     => ['nullable', 'string', 'max:150'],
            'patient_phone'       => ['nullable', 'string', 'max:50'],
            'service_date'        => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes'               => ['nullable', 'string', 'max:500'],
            'seller'              => ['nullable', 'string', 'max:150'],
            'referrer'            => ['nullable', 'string', 'max:150'],
            'procedures'          => ['required', 'array', 'min:1'],
            'procedures.*.medical_service_id'        => ['required', 'integer', 'exists:medical_services,id'],
            'procedures.*.unit_price'                => ['required', 'numeric', 'min:0'],
            'procedures.*.quantity'                  => ['required', 'numeric', 'min:0.0001'],
            'procedures.*.discount_type'             => ['nullable', 'string', 'in:fixed,percentage'],
            'procedures.*.discount_value'            => ['nullable', 'numeric', 'min:0'],
            'procedures.*.notes'                     => ['nullable', 'string', 'max:500'],
            'procedures.*.supplies'                  => ['nullable', 'array'],
            'procedures.*.supplies.*.warehouse_id'       => ['required_with:procedures.*.supplies', 'integer', 'exists:warehouses,id'],
            'procedures.*.supplies.*.generic_product_id' => ['required_with:procedures.*.supplies', 'integer', 'exists:product_generics,id'],
            'procedures.*.supplies.*.quantity'           => ['required_with:procedures.*.supplies', 'numeric', 'min:0.0001'],
        ];
    }

    public function messages(): array
    {
        return [
            'procedures.required'                        => 'Debe incluir al menos un procedimiento.',
            'procedures.min'                             => 'Debe incluir al menos un procedimiento.',
            'procedures.*.medical_service_id.exists'     => 'El procedimiento seleccionado no existe.',
            'procedures.*.discount_type.in'              => 'El tipo de descuento debe ser fixed o percentage.',
            'service_date.before_or_equal'               => 'La fecha de atención no puede ser futura.',
        ];
    }
}
