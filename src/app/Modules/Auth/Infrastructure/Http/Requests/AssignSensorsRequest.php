<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignSensorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sensor_ids' => ['required', 'array'],
            'sensor_ids.*' => ['integer', 'exists:sensors,id'],
        ];
    }
}
