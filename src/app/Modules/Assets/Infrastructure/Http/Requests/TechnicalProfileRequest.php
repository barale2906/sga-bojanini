<?php

declare(strict_types=1);

namespace App\Modules\Assets\Infrastructure\Http\Requests;

use App\Modules\Assets\Domain\Enums\AcquisitionMode;
use App\Modules\Assets\Domain\Enums\BiomedicalClass;
use App\Modules\Assets\Domain\Enums\PowerSource;
use App\Modules\Assets\Domain\Enums\RiskClass;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Hoja de vida técnica. Los campos que no aplican al tipo de equipo se descartan en el caso de uso. */
class TechnicalProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'manufacturer'          => ['nullable', 'string', 'max:150'],
            'country_of_origin'     => ['nullable', 'string', 'max:80'],
            'manufacture_year'      => ['nullable', 'integer', 'between:1900,' . ((int) date('Y') + 1)],
            'useful_life_years'     => ['nullable', 'integer', 'between:1,100'],
            'acquisition_mode'      => ['nullable', Rule::enum(AcquisitionMode::class)],
            'technical_specs'       => ['nullable', 'array'],
            'technical_specs.*'     => ['nullable', 'string', 'max:255'],
            'accessories'           => ['nullable', 'string'],
            'usage_recommendations' => ['nullable', 'string'],

            'invima_registry'       => ['nullable', 'string', 'max:80'],
            'risk_class'            => ['nullable', Rule::enum(RiskClass::class)],
            'biomedical_class'      => ['nullable', Rule::enum(BiomedicalClass::class)],
            'power_source'          => ['nullable', Rule::enum(PowerSource::class)],
            'voltage'               => ['nullable', 'string', 'max:40'],
            'current'               => ['nullable', 'string', 'max:40'],
            'power'                 => ['nullable', 'string', 'max:40'],
            'frequency'             => ['nullable', 'string', 'max:40'],
            'requires_calibration'  => ['sometimes', 'boolean'],
            'operating_conditions'  => ['nullable', 'string'],

            'processor'             => ['nullable', 'string', 'max:120'],
            'ram'                   => ['nullable', 'string', 'max:40'],
            'storage'               => ['nullable', 'string', 'max:80'],
            'operating_system'      => ['nullable', 'string', 'max:80'],
            'ip_address'            => ['nullable', 'ip'],
            'mac_address'           => ['nullable', 'mac_address'],
            'hostname'              => ['nullable', 'string', 'max:80'],
            'licenses'              => ['nullable', 'array'],
            'licenses.*.name'       => ['required', 'string', 'max:120'],
            'licenses.*.key'        => ['nullable', 'string', 'max:255'],
            'licenses.*.expires_at' => ['nullable', 'date'],
        ];
    }
}
