<?php

declare(strict_types=1);

namespace App\Modules\Monitoring\Infrastructure\Http\Requests;

use App\Modules\Monitoring\Application\DTOs\CalibrationCertificateData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Creación de un sensor. Puede llegar como JSON o como multipart cuando
 * se adjunta el certificado de calibración (opcional: si no se tiene a la
 * mano se carga después desde el historial de certificados).
 */
class StoreSensorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'zone_id' => ['required', 'integer', 'exists:zones,id'],
            'code'    => ['nullable', 'string', 'max:50', 'unique:sensors,code'],
            'name'    => ['required', 'string', 'max:255'],
            'type'    => ['required', 'in:temperature,humidity'],
            'unit'    => ['required', 'string', 'max:10'],

            'certificate_file'               => ['nullable', 'required_with:certificate_expires_at', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:' . StoreCalibrationCertificateRequest::MAX_KB],
            'certificate_expires_at'         => ['nullable', 'required_with:certificate_file', 'date', 'after_or_equal:certificate_calibrated_at'],
            'certificate_calibrated_at'      => ['nullable', 'date', 'before_or_equal:today'],
            'certificate_certificate_number' => ['nullable', 'string', 'max:100'],
            'certificate_laboratory'         => ['nullable', 'string', 'max:150'],
            'certificate_notes'              => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return CalibrationCertificateMessages::messages('certificate_') + [
            'certificate_file.required_with'       => 'Adjunte el archivo del certificado o deje vacía la fecha de vencimiento.',
            'certificate_expires_at.required_with' => 'Indique la fecha de vencimiento del certificado adjunto.',
        ];
    }

    public function attributes(): array
    {
        return CalibrationCertificateMessages::attributes('certificate_');
    }

    public function certificateData(): ?CalibrationCertificateData
    {
        if (! $this->hasFile('certificate_file')) {
            return null;
        }

        return new CalibrationCertificateData(
            file:              $this->file('certificate_file'),
            expiresAt:         $this->validated('certificate_expires_at'),
            calibratedAt:      $this->validated('certificate_calibrated_at'),
            certificateNumber: $this->validated('certificate_certificate_number'),
            laboratory:        $this->validated('certificate_laboratory'),
            notes:             $this->validated('certificate_notes'),
        );
    }
}
