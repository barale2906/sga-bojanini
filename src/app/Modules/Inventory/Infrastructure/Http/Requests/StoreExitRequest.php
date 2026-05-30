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
            'product_id'          => ['required', 'integer', 'exists:products,id'],
            'warehouse_id'        => ['required', 'integer', 'exists:warehouses,id'],
            'location_id'         => ['nullable', 'integer', 'exists:locations,id'],
            'quantity'            => ['required', 'integer', 'min:1'],
            'reason'              => ['nullable', 'string'],
            // Centro de costo: siempre requerido en salidas
            'cost_center_id'      => ['required', 'integer', 'exists:cost_centers,id'],
            // Servicio médico: requerido si el centro es externo (validado en el use case)
            'service_id'          => ['nullable', 'integer', 'exists:medical_services,id'],
            // Datos del paciente: solo aplican para centros externos (validados en el use case)
            'patient_document'    => ['nullable', 'string', 'max:50'],
            'patient_external_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
