<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Requests;

use App\Modules\Assets\Application\DTOs\CompleteMaintenanceData;
use App\Modules\Assets\Domain\Enums\MaintenanceResult;
use App\Modules\Assets\Domain\Enums\PerformedByType;
use App\Modules\Auth\Infrastructure\Http\Requests\Concerns\ValidatesSigners;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registro de la ejecución de un mantenimiento. Es obligatorio indicar qué
 * se hizo y quién lo hizo; las firmas son opcionales (para técnico externo
 * se puede enviar `delivered_by` en pad y `received_by` con PIN).
 */
class CompleteMaintenanceRequest extends FormRequest
{
    use ValidatesSigners;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'performed_at'              => ['required', 'date', 'before_or_equal:now'],
            'performed_by_type'         => ['required', Rule::enum(PerformedByType::class)],
            'technician_id'             => ['nullable', 'integer', 'exists:users,id', 'required_if:performed_by_type,internal'],
            'supplier_id'               => ['nullable', 'integer', 'exists:suppliers,id', 'required_if:performed_by_type,external'],
            'external_technician_name'  => ['nullable', 'string', 'max:150', 'required_if:performed_by_type,external'],
            'activities_performed'      => ['required', 'string'],
            'findings'                  => ['nullable', 'string'],
            'recommendations'           => ['nullable', 'string'],
            'result'                    => ['required', Rule::enum(MaintenanceResult::class)],
            'parts_replaced'            => ['nullable', 'array'],
            'parts_replaced.*.name'     => ['required', 'string', 'max:150'],
            'parts_replaced.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'parts_replaced.*.cost'     => ['nullable', 'numeric', 'min:0'],
            'cost'                      => ['nullable', 'numeric', 'min:0'],
            ...$this->signerRules(receiverRequired: false),
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->signerMessages(),
            'technician_id.required_if'            => 'Indique el técnico interno que realizó el mantenimiento.',
            'supplier_id.required_if'              => 'Indique el proveedor que realizó el mantenimiento.',
            'external_technician_name.required_if' => 'Indique el nombre del técnico externo.',
            'activities_performed.required'        => 'Describa las actividades realizadas.',
        ];
    }

    public function toData(): CompleteMaintenanceData
    {
        return CompleteMaintenanceData::fromArray($this->validated());
    }
}
