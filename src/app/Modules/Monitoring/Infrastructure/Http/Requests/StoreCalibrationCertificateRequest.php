<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Requests;

use App\Modules\Monitoring\Application\DTOs\CalibrationCertificateData;
use Illuminate\Foundation\Http\FormRequest;

/** Carga de un certificado de calibración (multipart/form-data). */
class StoreCalibrationCertificateRequest extends FormRequest
{
    /** Tamaño máximo por archivo en KB (10 MB). */
    public const MAX_KB = 10240;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'               => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:' . self::MAX_KB],
            'expires_at'         => ['required', 'date', 'after_or_equal:calibrated_at'],
            'calibrated_at'      => ['nullable', 'date', 'before_or_equal:today'],
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'laboratory'         => ['nullable', 'string', 'max:150'],
            'notes'              => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return CalibrationCertificateMessages::messages();
    }

    public function attributes(): array
    {
        return CalibrationCertificateMessages::attributes();
    }

    public function toData(): CalibrationCertificateData
    {
        return new CalibrationCertificateData(
            file:              $this->file('file'),
            expiresAt:         $this->validated('expires_at'),
            calibratedAt:      $this->validated('calibrated_at'),
            certificateNumber: $this->validated('certificate_number'),
            laboratory:        $this->validated('laboratory'),
            notes:             $this->validated('notes'),
        );
    }
}
