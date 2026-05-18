<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAlertRuleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'condition_type'         => ['required', 'in:above,below,trend_up,trend_down,out_of_range'],
            'threshold'              => ['nullable', 'numeric'],
            'consecutive_readings'   => ['integer', 'min:1', 'max:100'],
            'notification_channels'  => ['required', 'array', 'min:1'],
            'notification_channels.*' => ['in:internal,email,push'],
        ];
    }
}
