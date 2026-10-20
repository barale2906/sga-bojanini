<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Requests;

use App\Modules\Assets\Application\DTOs\MaintenancePlanData;
use App\Modules\Assets\Domain\Enums\FrequencyUnit;
use App\Modules\Assets\Domain\Enums\MaintenanceType;
use App\Modules\Assets\Domain\Enums\PerformedByType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenancePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maintenance_type'  => ['required', Rule::in(array_map(fn ($t) => $t->value, MaintenanceType::plannable()))],
            'frequency_value'   => ['required', 'integer', 'min:1', 'max:3650'],
            'frequency_unit'    => ['required', Rule::enum(FrequencyUnit::class)],
            'start_date'        => ['required', 'date'],
            'performed_by_type' => ['required', Rule::enum(PerformedByType::class)],
            'technician_id'     => ['nullable', 'integer', 'exists:users,id'],
            'supplier_id'       => ['nullable', 'integer', 'exists:suppliers,id'],
            'notes'             => ['nullable', 'string'],
            'is_active'         => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['maintenance_type.in' => 'El mantenimiento correctivo no se planifica: regístrelo como orden puntual.'];
    }

    public function toData(): MaintenancePlanData
    {
        return MaintenancePlanData::fromArray($this->validated());
    }
}
