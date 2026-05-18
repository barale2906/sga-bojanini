<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSensorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sensorId = $this->route('sensor');

        return [
            'zone_id' => ['required', 'integer', 'exists:zones,id'],
            'code'    => ['required', 'string', 'max:50', Rule::unique('sensors', 'code')->ignore($sensorId)],
            'name'    => ['required', 'string', 'max:255'],
            'type'    => ['required', 'in:temperature,humidity'],
            'unit'    => ['required', 'string', 'max:10'],
        ];
    }
}
