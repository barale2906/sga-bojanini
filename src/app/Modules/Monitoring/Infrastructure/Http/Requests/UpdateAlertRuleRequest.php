<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAlertRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'condition_type'         => ['sometimes', 'in:above,below,trend_up,trend_down,out_of_range'],
            'threshold'              => ['nullable', 'numeric'],
            'consecutive_readings'   => ['sometimes', 'integer', 'min:1', 'max:100'],
            'notification_channels'  => ['sometimes', 'array', 'min:1'],
            'notification_channels.*' => ['in:internal,email,push'],
            'is_active'              => ['sometimes', 'boolean'],
        ];
    }
}
