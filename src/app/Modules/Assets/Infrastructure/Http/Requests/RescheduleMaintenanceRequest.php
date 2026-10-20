<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduled_date'   => ['required', 'date'],
            'reported_failure' => ['nullable', 'string'],
        ];
    }
}
