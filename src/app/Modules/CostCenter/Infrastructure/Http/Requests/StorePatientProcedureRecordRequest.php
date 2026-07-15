<?php

declare(strict_types=1);

namespace App\Modules\CostCenter\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientProcedureRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medical_service_id'  => ['required', 'integer', 'exists:medical_services,id'],
            'patient_external_id' => ['required', 'string', 'max:100'],
            'patient_document'    => ['required', 'string', 'max:50'],
            'patient_first_name'  => ['required', 'string', 'max:100'],
            'patient_last_name'   => ['required', 'string', 'max:100'],
            'quantity'            => ['required', 'numeric', 'min:0.0001'],
            'unit_price'          => ['required', 'numeric', 'min:0'],
            'service_date'        => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes'                => ['nullable', 'string', 'max:500'],
            'seller'               => ['nullable', 'string', 'max:150'],
            'referrer'             => ['nullable', 'string', 'max:150'],
            'movement_document_id' => ['nullable', 'integer', 'exists:movement_documents,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_first_name.required'      => 'Los nombres del paciente son obligatorios.',
            'patient_last_name.required'       => 'Los apellidos del paciente son obligatorios.',
            'quantity.min'                     => 'La cantidad debe ser mayor a cero.',
            'service_date.before_or_equal'     => 'La fecha de atención no puede ser futura.',
            'medical_service_id.exists'        => 'El procedimiento seleccionado no existe.',
        ];
    }
}
