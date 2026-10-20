<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Requests;

use App\Modules\Assets\Application\DTOs\ScheduleMaintenanceData;
use App\Modules\Assets\Domain\Enums\MaintenanceType;
use App\Modules\Assets\Domain\Enums\PerformedByType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScheduleMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maintenance_type'  => ['required', Rule::enum(MaintenanceType::class)],
            'scheduled_date'    => ['required', 'date'],
            'reported_failure'  => ['nullable', 'string', 'required_if:maintenance_type,corrective'],
            'performed_by_type' => ['nullable', Rule::enum(PerformedByType::class)],
            'technician_id'     => ['nullable', 'integer', 'exists:users,id'],
            'supplier_id'       => ['nullable', 'integer', 'exists:suppliers,id'],
        ];
    }

    public function messages(): array
    {
        return ['reported_failure.required_if' => 'Describa la falla reportada para el mantenimiento correctivo.'];
    }

    public function toData(): ScheduleMaintenanceData
    {
        return ScheduleMaintenanceData::fromArray($this->validated());
    }
}
